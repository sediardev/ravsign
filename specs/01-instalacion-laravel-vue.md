# SPEC 01 — Instalación base de Laravel 13 con Vue para RavSign

> **Status:** Aprobado
> **Depends on:** ninguna
> **Date:** 2026-09-29
> **Objective:** Dejar instalado y funcionando en local y en hosting compartido un proyecto Laravel 13 con Vue (Inertia), login, MySQL y las dependencias de PDF y firma, sin ninguna lógica de documentos todavía.

---

## Por qué existe esta spec

RavSign será una app tipo Signwell: un usuario logueado sube documentos, define posiciones de firma y comparte un link para que otra persona firme en esas posiciones (subiendo una imagen o dibujando en un canvas). Antes de construir eso hay que fijar el stack y las restricciones del hosting compartido, que condicionan el resto de las specs.

Restricciones del entorno de producción (confirmadas):

- PHP 8.4 en el hosting (el panel soporta hasta 8.5; hay que seleccionar 8.4 en el panel). Local: PHP 8.4.10.
- MySQL. Acceso por SSH con Composer disponible. No hay Node en el servidor.
- Sin workers persistentes ni Redis. Solo `cron` si hiciera falta.
- `exec` y `symlink` están en `disable_functions` del panel y el usuario los habilitará quitándolos de la lista.
- Extensiones disponibles: `gd`, `imagick`, `fileinfo`, `mbstring`, `zip`, `intl`, `bcmath`, `dom`, `pdo_mysql`, entre otras.
- `upload_max_filesize` y `post_max_size` en 2048M, `memory_limit` 2048M, `max_execution_time` 360 s.

---

## Scope

**In:**

- Proyecto Laravel 13 con el starter kit oficial Vue (Inertia + Vue 3 + Tailwind + autenticación con login, registro y reseteo de contraseña).
- Instalación en el repositorio existente `D:\Repositorios\ravsign`, conservando `.git` y el historial.
- Base de datos MySQL configurada en `.env.example`, con migraciones del starter kit ejecutadas.
- Drivers: `SESSION_DRIVER=database`, `CACHE_STORE=database`, `QUEUE_CONNECTION=sync`, `MAIL_MAILER=log`.
- Disco privado para documentos (`storage/app/private`) y symlink `public/storage` solo para archivos públicos.
- Dependencias PHP: `setasign/fpdi` y `setasign/fpdf` para estampar firmas en PDF.
- Dependencias JS: `pdfjs-dist` (visor de PDF) y `signature_pad` (firma en canvas).
- Build de Vue compilado en local y commiteado (`public/build`). `vendor/` y `node_modules/` ignorados.
- Fijar `config.platform.php` a `8.4` en `composer.json`.
- Documentar en `README.md` cómo instalar en local y cómo desplegar en el hosting.

**Out of scope (para specs futuras):**

- Tablas y modelos de documentos, posiciones de firma y solicitudes de firma.
- Pantallas de subida de documentos, editor de posiciones y página pública de firma.
- Estampado real de firmas en el PDF (solo se instala la librería).
- Normalización de PDFs con Ghostscript (ver Decisiones).
- Configuración de SMTP y envío de correos reales.
- Verificación de correo, 2FA y roles.
- Colas con workers y Redis.
- Tests automatizados de negocio (solo los que trae el starter kit).

---

## Data model

Esta spec no introduce estructuras de datos propias. Solo se ejecutan las migraciones que trae el starter kit (`users`, `password_reset_tokens`, `sessions`, `cache`, `jobs`, etc.).

Variables de entorno relevantes en `.env.example`:

```dotenv
APP_NAME=RavSign
APP_LOCALE=es
DB_CONNECTION=mysql
DB_DATABASE=ravsign
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=sync
MAIL_MAILER=log
FILESYSTEM_DISK=local
```

---

## Implementation plan

1. Crear el proyecto en una carpeta temporal fuera del repo con `laravel new` usando el starter kit Vue, y verificar que resuelve a Laravel 13.
2. Copiar los archivos generados a `D:\Repositorios\ravsign` sin tocar `.git`, y conservar el `README.md` existente. Commit: proyecto base.
3. Ajustar `.env.example` con los valores de la sección Data model, crear `.env` local y la base MySQL `ravsign`. Correr `php artisan key:generate` y `php artisan migrate`.
4. Agregar en `composer.json` `"config": { "platform": { "php": "8.4" } }` y ejecutar `composer update` para regenerar `composer.lock` compatible con el hosting.
5. Instalar `composer require setasign/fpdi setasign/fpdf`.
6. Instalar `npm install pdfjs-dist signature_pad`.
7. Ajustar `.gitignore`: ignorar `vendor/`, `node_modules/`, `.env`, y **no** ignorar `public/build`.
8. Ejecutar `npm run build` y commitear `public/build`.
9. Ejecutar `php artisan storage:link` y confirmar que un archivo en `storage/app/private` no es accesible por URL.
10. Escribir en `README.md` los pasos de instalación local y el flujo de deploy: `git pull`, `composer install --no-dev -o`, `php artisan migrate --force`, `php artisan config:cache route:cache view:cache`, `ln -s` / `storage:link`.

---

## Acceptance criteria

- [ ] `composer show laravel/framework` en el repo indica versión 13.x.
- [ ] `php artisan serve` levanta la app y `/` responde con HTTP 200.
- [ ] Se puede registrar un usuario, cerrar sesión e iniciar sesión de nuevo desde la interfaz Vue.
- [ ] `php artisan migrate:status` muestra todas las migraciones como ejecutadas contra MySQL.
- [ ] `composer.json` contiene `"platform": { "php": "8.4" }` y `composer install --no-dev` funciona sin errores.
- [ ] `composer show setasign/fpdi` y `npm ls pdfjs-dist signature_pad` listan las tres dependencias.
- [ ] `public/build/manifest.json` está trackeado en git y `git check-ignore public/build` no devuelve nada.
- [ ] `vendor/`, `node_modules/` y `.env` están ignorados por git.
- [ ] La app renderiza en el navegador usando solo `public/build` (sin `npm run dev` corriendo).
- [ ] `.env.example` contiene exactamente las variables de la sección Data model.
- [ ] `README.md` documenta instalación local y deploy en hosting.
- [ ] Un archivo colocado en `storage/app/private` no devuelve 200 al pedirlo por URL (404 en `/private/...`, 403 en `/storage/...`).

---

## Decisions

- **Yes:** Inertia + Vue (starter kit oficial). Trae el login y evita construir una API separada. Un solo despliegue PHP.
- **No:** Vue SPA + API con Sanctum. Más trabajo de base sin beneficio en esta etapa.
- **Yes:** `public/build` commiteado. El hosting no tiene Node, así que el build se hace en local.
- **Yes:** `vendor/` ignorado, `composer install` en el servidor por SSH. Mantiene el repo liviano.
- **Yes:** MySQL también en local. Mismo motor que producción evita diferencias en migraciones.
- **No:** SQLite. Es el default de Laravel, pero producción usa MySQL.
- **Yes:** sesiones y caché en `database`, colas en `sync`. Sin workers ni Redis, es lo que funciona en compartido. Las colas con workers irían en otra spec.
- **Yes:** `MAIL_MAILER=log`. El usuario configurará el correo más adelante.
- **Yes:** PHP 8.4 en local y en el hosting, con `config.platform.php = 8.4` para que el `composer.lock` resuelva siempre para esa versión.
- **No:** PHP 8.5. El hosting lo soporta, pero local corre 8.4 y no hay razón para adelantarse.
- **Yes:** `setasign/fpdi` + `fpdf` para estampar firmas en el servidor. Es PHP puro, sin coste y el servidor controla el PDF final, lo que es más auditable.
- **No:** `pdf-lib` en el navegador. El documento final lo generaría el cliente y el servidor no lo controla.
- **No:** parser comercial de FPDI. Requiere licencia de pago.
- **Yes (pendiente para spec de estampado):** con `exec` habilitado se podrá usar Ghostscript, si el hosting lo tiene, para normalizar PDFs 1.5+ a 1.4 antes de FPDI. Se verifica con `gs --version` por SSH. No se implementa aquí.
- **Yes:** aceptar el 403 de la ruta `/storage/{path}` del disco `local` (`serve => true`). Solo entrega archivos con URL firmada, es el default de Laravel y deja disponibles las URLs temporales firmadas.
- **No:** `serve => false` en el disco `local` para forzar 404. Desactivaría las URLs firmadas sin ganar seguridad real.
- **Yes:** PDFs en disco privado y servidos por controlador. Son documentos sensibles y no deben ser accesibles por URL directa.
- **Yes:** `symlink` y `exec` se habilitan en el panel del hosting antes del despliegue. Solo el symlink es necesario para esta spec (`storage:link`).

---

## Risks

| Risk | Mitigation |
| --- | --- |
| FPDI gratuito no lee PDFs con xref comprimido (PDF 1.5+, comunes en Word y Google Docs). | No se estampa nada en esta spec. La spec de estampado validará al subir y normalizará con Ghostscript si está instalado. |
| El hosting queda en otra versión de PHP que local (8.4). | Seleccionar PHP 8.4 en el panel y fijar `config.platform.php = 8.4` en `composer.json`. |
| Olvidar recompilar antes de subir a git y desplegar un build viejo. | El README indica correr `npm run build` y commitear `public/build` antes de cada deploy. |
| `symlink` sigue deshabilitado y `storage:link` falla en el servidor. | Quitar `symlink` de `disableFunctions` en el panel, o crear el enlace a mano con `ln -s`. |
| Habilitar `exec` amplía la superficie de ataque del hosting. | Solo se usará con argumentos escapados (`escapeshellarg`) y en una spec posterior. |

---

## What is **not** in this spec

- Modelos, tablas y pantallas de documentos, posiciones de firma o links de firma.
- Estampado de firmas en PDF y normalización con Ghostscript.
- Envío real de correos.
- Colas con workers, Redis, roles, 2FA.

Cada uno de estos puntos, si se hace, va en su propia spec.
