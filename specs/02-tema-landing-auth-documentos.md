# SPEC 02 — Tema visual, landing, autenticación y listado de documentos

> **Status:** Implementado
> **Depends on:** SPEC 01
> **Date:** 2026-09-29
> **Objective:** Aplicar el diseño del artifact de Ravsign (paleta, fuentes, logos) al starter kit y construir en Vue la landing, el registro/login, el listado de Documentos y los ajustes, con datos de ejemplo y sin backend de documentos.

---

## Por qué existe esta spec

El diseño completo (artifact `https://claude.ai/artifact/3uiQ7uzUKAkkUA1BYz4165`) tiene 6 pantallas y varios modales. Es demasiado para una sola spec, así que se divide en dos:

- **SPEC 02 (esta):** tema, landing, registro/login, layout de la app, listado de Documentos y ajustes.
- **SPEC 03:** editor de campos, pantalla de firma y modales, sobre un PDF real con pdf.js.

El artifact es un prototipo con estado en memoria. Aquí se llevan sus pantallas a rutas Inertia reales que reciben datos de ejemplo desde controladores, de modo que cuando exista backend solo cambie el origen de los datos.

Referencias del diseño:

- Colores: azul marino `#1a3560` (texto y sidebar oscuro), turquesa `#1792bb` (acción primaria, hover `#1380a5`), gris `#6b7078` (texto secundario), bordes `#d5dbe3` y `#e3e7ed`, fondo de app `#f6f8fb`, fondo suave turquesa `#eaf4f8`.
- Tipografías: Poppins 500/600/700 para títulos y marca, Montserrat 400/500/600/700 para el resto.
- Radios: 10 px en inputs y botones pequeños, 12 px en botones grandes, 24 px en bloques grandes.
- Breakpoint móvil: menos de 760 px de ancho.
- Logos ya presentes en `public/img/`: `icon.svg` (isotipo), `logo.svg` y `logo-letters.svg`.

---

## Scope

**In:**

- Tema global: variables de color del starter (`--primary`, `--foreground`, `--border`, `--sidebar-*`, etc.) mapeadas a la paleta del diseño, fuentes autohospedadas y radios.
- Solo modo claro. Se quita el modo oscuro y la pestaña Apariencia.
- Marca: isotipo `public/img/icon.svg` + texto "Ravsign" en Poppins 600 en cabeceras, sidebar y pies. `APP_NAME=Ravsign`.
- Landing en `/` con cabecera, hero, "Cómo funciona", "Funciones", bloque de llamada a la acción y pie, con los textos del artifact.
- Login y Registro con el layout de dos columnas del artifact: formulario a la izquierda y panel azul marino con los 3 pasos a la derecha (solo en escritorio). El login reutiliza el layout y el estilo del registro.
- Restyle de las páginas de auth restantes del starter: olvidé contraseña, restablecer contraseña y confirmar contraseña.
- Registro con nombre completo, correo y contraseña. Se omite el campo "Empresa" del artifact.
- Desactivar la verificación de correo: sin `MustVerifyEmail`, sin la feature de Fortify y sin middleware `verified`. Tras registrarse, el usuario va directo a Documentos.
- Layout de la app: sidebar de 240 px en escritorio (marca, enlace "Documentos", avatar con inicial, nombre, "Cerrar sesión" y acceso a Ajustes) y cabecera compacta en móvil.
- Pantalla Documentos en `/documents`: título, botón "Subir documento", zona de arrastre, filtros con contadores (Todos, Borradores, Pendientes, Completados), tabla en escritorio y tarjetas en móvil, y estado vacío "No hay documentos en esta vista."
- Datos de ejemplo servidos por el controlador (los 4 documentos del artifact).
- La zona de subida y el botón "Subir documento" solo muestran un aviso; no abren el selector ni suben nada.
- Restyle de los ajustes (perfil, cambiar contraseña, eliminar cuenta) con los tokens y componentes nuevos, sin cambiar su comportamiento.
- Quitar la verificación en dos pasos (2FA): feature y limitador de Fortify, `TwoFactorAuthenticatable` y columnas `two_factor_*` en `User` y en su factory, `TwoFactorAuthenticationRequest`, la migración `add_two_factor_columns_to_users_table`, la página `TwoFactorChallenge.vue`, los componentes `ManageTwoFactor`, `TwoFactorRecoveryCodes` y `TwoFactorSetupModal`, el composable `useTwoFactorAuth`, el componente `ui/input-otp` y las dependencias `vue-input-otp` y `AlertError`.
- Quitar passkeys: feature de Fortify, interfaz y trait en `User`, componentes Vue, migración, ruta `.well-known/passkey-endpoints`, limitador `passkeys` y dependencia `@laravel/passkeys`.
- Favicon de Ravsign: `public/img/icon.svg` como icono SVG, y `public/favicon.ico` y `public/apple-touch-icon.png` generados desde ese isotipo.
- Textos de las pantallas en español.
- Build recompilado y `public/build` actualizado.

**Out of scope (para specs futuras):**

- Editor de campos, pantalla de firma, modal de enlaces de firma y toda la interacción con documentos (SPEC 03).
- Subir documentos, guardar documentos o firmantes en la base de datos.
- Que las filas del listado sean clicables (se activan en SPEC 03).
- Campo "Empresa" y cualquier columna nueva en `users`.
- Páginas de Términos de servicio y Política de privacidad (los textos existen, los enlaces quedan sin destino).
- Envío real de correos y verificación de email (se reactiva en la spec de correo).
- Mensajes de validación de Laravel traducidos al español.
- Modo oscuro.
- Inicio de sesión con passkeys (se elimina; si se quiere, va en su propia spec).

---

## Data model

No hay tablas nuevas ni cambios de migración. Los documentos son datos de ejemplo definidos en PHP y tipados en TypeScript.

```ts
// resources/js/types/documents.ts
export type DocumentStatus = 'borrador' | 'pendiente' | 'completado';
export type FieldType = 'firma' | 'iniciales' | 'fecha' | 'nombre';

export interface Signer {
    id: string; // 's1'
    name: string; // 'Carlos Ruiz'
    siglas: string; // 'CR', máximo 4 caracteres
    email: string;
    color: string; // uno de #1792bb, #7a4fc9, #d27a1f, #2f9e6b
}

export interface SignField {
    id: string; // 'f1'
    type: FieldType;
    signerId: string;
    page: number; // base 0
    x: number; // % del ancho de la página (0-100)
    y: number; // % del alto de la página (0-100)
    value: string | null; // data URL de imagen, o texto
}

export interface DocumentItem {
    id: string; // uuid, no el id incremental interno (ver SPEC 03)
    name: string;
    status: DocumentStatus;
    date: string; // ya formateada, '29 sep 2026'
    signers: Signer[];
    fields: SignField[];
}
```

Esta spec solo muestra `name`, `status`, `date` y los nombres de `signers`. `fields` se define ahora para no reescribir el tipo en SPEC 03.

Origen de los datos: `App\Support\MockData::documents()` devuelve los 4 documentos del artifact:

| id | name | status | date | signers |
| --- | --- | --- | --- | --- |
| 1 | Acuerdo de servicios — Nómada Studio.pdf | borrador | 29 sep 2026 | Carlos Ruiz, Lucía Fernández |
| 2 | Contrato de arrendamiento Local 4B.pdf | pendiente | 27 sep 2026 | Marta Gil |
| 3 | NDA Proveedores 2026.pdf | completado | 21 sep 2026 | Jorge Peña |
| 4 | Propuesta comercial Q4.pdf | borrador | 18 sep 2026 | Ana Morales |

Los campos (`fields`) de cada documento son los de `initialDocs` del artifact: firma en `x: 11.1, y: 63.6, page: 1` para cada firmante, con `x: 56.9` para la segunda firmante del documento 1, y `value` con el texto del firmante solo en el documento completado.

Etiquetas de estado (texto, fondo, color del texto):

- `borrador`: "Borrador", `#eef0f3`, `#4b515a`.
- `pendiente`: "Pendiente de firma", `#fdf1e2`, `#9a5a12`.
- `completado`: "Completado", `#e3f3f9`, `#0f6d8e`.

Rutas nuevas y modificadas:

| Ruta | Nombre | Middleware | Página |
| --- | --- | --- | --- |
| `GET /` | `home` | ninguno | `Welcome.vue` |
| `GET /documents` | `documents.index` | `auth` | `documents/Index.vue` |
| `GET /dashboard` | `dashboard` | `auth` | redirige a `documents.index` |

---

## Implementation plan

1. Instalar `@fontsource/montserrat` y `@fontsource/poppins` con npm. Importar los pesos 400/500/600/700 (Montserrat) y 500/600/700 (Poppins), subconjunto latino, en `resources/js/app.ts`. En `resources/css/app.css` reemplazar `--font-sans` por Montserrat, agregar `--font-display` con Poppins y redefinir las variables de color y `--radius` con la paleta del diseño. Verificar que la app carga sin peticiones a Google Fonts ni a Instrument Sans.
2. Quitar el modo oscuro: eliminar `resources/js/composables/useAppearance.ts`, `resources/js/components/AppearanceTabs.vue`, `resources/js/pages/settings/Appearance.vue`, la ruta `appearance.edit` en `routes/settings.php`, el enlace de Apariencia en el layout de ajustes y el script de tema en `resources/views/app.blade.php`. Verificar que la app renderiza siempre en claro.
3. Cambiar `AppLogo.vue` y `AppLogoIcon.vue` para usar `/img/icon.svg` con el texto "Ravsign" en Poppins 600. Poner `APP_NAME=Ravsign` en `.env.example` y en `.env`.
4. Desactivar la verificación de correo: quitar `MustVerifyEmail` de `app/Models/User.php`, `Features::emailVerification()` de `config/fortify.php` y el middleware `verified` de `routes/web.php` y `routes/settings.php`. Eliminar `resources/js/pages/auth/VerifyEmail.vue`. Ajustar los tests del starter que dependan de la verificación para que `php artisan test` pase.
5. Reescribir `resources/js/layouts/auth/AuthSplitLayout.vue` con el layout de dos columnas del artifact y el panel azul marino con los pasos "Carga tus PDF y previsualízalos", "Arrastra campos de firma y asígnalos a cada firmante" y "Firma con un trazo o con una imagen de tu firma". Usarlo en `Login.vue` y `Register.vue` con los textos del artifact ("Crea tu cuenta", "Empieza a enviar documentos para firma.", "¿Ya tienes cuenta? Inicia sesión") y sin el campo Empresa. Verificar registro e inicio de sesión desde la UI.
6. Aplicar el mismo layout y estilo a `ForgotPassword.vue`, `ResetPassword.vue` y `ConfirmPassword.vue`, con textos en español.
7. Crear `app/Support/MockData.php` con `documents()` y `resources/js/types/documents.ts`. Crear `app/Http/Controllers/DocumentController.php` con `index()` que devuelve `Inertia::render('documents/Index', ['documents' => MockData::documents()])`. Registrar `GET /documents` (`documents.index`, middleware `auth`) y convertir `dashboard` en una redirección a `documents.index`. Que Fortify redirija a `/documents` tras login y registro (`config/fortify.php`, opción `home`).
8. Rehacer `resources/js/layouts/app/AppSidebarLayout.vue` con el sidebar del diseño (240 px, borde derecho, marca arriba, "Documentos" activo, bloque de usuario abajo con menú que incluye Ajustes y Cerrar sesión) y la cabecera móvil bajo 760 px. Eliminar `Dashboard.vue`.
9. Crear `resources/js/pages/documents/Index.vue`: título "Documentos", botón "Subir documento", zona de arrastre con los textos de escritorio y móvil, filtros con contadores calculados en el cliente, tabla en escritorio y tarjetas en móvil, chips de estado y estado vacío. El botón y la zona lanzan un aviso (componente `sonner` del starter) con el texto "La subida de documentos estará disponible pronto." y no abren el selector de archivos.
10. Reemplazar `resources/js/pages/Welcome.vue` por la landing del artifact: cabecera con "Iniciar sesión" (`/login`) y "Empezar gratis" (`/register`), hero, "Cómo funciona" con los pasos 01-03, "Funciones", bloque de llamada final y pie. El botón secundario "Probar el editor" apunta a `/login`.
11. Aplicar el tema nuevo a `layouts/settings/Layout.vue`, `settings/Profile.vue`, `settings/Security.vue` y al componente `DeleteUser`: tipografía, colores, radios, botones y campos como en el resto de pantallas, con textos en español. Sin cambiar rutas ni comportamiento. Quitar 2FA (paso adicional): todo lo listado en el alcance. Quitar passkeys (paso adicional): `Features::passkeys` y su configuración en `config/fortify.php`, `PasskeyUser` y `PasskeyAuthenticatable` en `User`, la migración `create_passkeys_table`, `ManagePasskeys`, `PasskeyItem`, `PasskeyRegister` y `PasskeyVerify`, la ruta `.well-known/passkey-endpoints` y `@laravel/passkeys` (npm). Poner el favicon: `<link rel="icon">` a `/img/icon.svg` en `resources/views/app.blade.php`, y regenerar `public/favicon.ico` y `public/apple-touch-icon.png` desde el isotipo, eliminando el `favicon.svg` de Laravel.
12. Ejecutar `npm run build` y dejar `public/build` actualizado para commitear.

---

## Acceptance criteria

- [x] `php artisan test` pasa completo.
- [x] `npm run types:check` termina sin errores.
- [x] `npm run build` termina sin errores y `public/build/manifest.json` está actualizado.
- [x] Ninguna página hace peticiones a `fonts.googleapis.com`, `fonts.bunny.net` ni se usa la fuente Instrument Sans.
- [x] El texto de los títulos se renderiza con Poppins y el cuerpo con Montserrat (verificable con `getComputedStyle`).
- [x] El botón primario tiene fondo `rgb(23, 146, 187)` y cambia a `rgb(19, 128, 165)` en hover.
- [x] `GET /` responde 200 sin sesión y muestra "Firma y envía documentos en minutos.", "Cómo funciona" y "Funciones".
- [x] Los botones "Iniciar sesión" y "Empezar gratis" de la landing llevan a `/login` y `/register`.
- [x] Registrar un usuario nuevo con nombre, correo y contraseña redirige a `/documents` sin pedir verificación de correo.
- [x] El formulario de registro no tiene campo "Empresa".
- [x] El panel azul marino con los 3 pasos aparece en `/login` y `/register` a 1280 px de ancho y no aparece a 375 px.
- [x] Cerrar sesión desde el sidebar lleva a `/` y `GET /documents` sin sesión redirige a `/login`.
- [x] `GET /dashboard` con sesión redirige a `/documents`.
- [x] `/documents` muestra 4 filas con los nombres, estados y fechas de la tabla del Data model.
- [x] Los contadores de filtros muestran Todos 4, Borradores 2, Pendientes 1 y Completados 1, y cada filtro deja solo las filas de su estado.
- [x] Un filtro sin resultados muestra "No hay documentos en esta vista."
- [x] Hacer clic en "Subir documento" o soltar un archivo en la zona muestra el aviso y no abre el selector de archivos ni envía peticiones al servidor.
- [x] A 375 px de ancho `/`, `/login`, `/register` y `/documents` no tienen scroll horizontal, y `/documents` muestra tarjetas en lugar de tabla.
- [x] `/settings/profile` y `/settings/security` cargan con el tema nuevo y siguen guardando cambios.
- [x] La ruta `/settings/appearance` responde 404.
- [x] `resources/js/pages/auth/VerifyEmail.vue` y `resources/js/pages/Dashboard.vue` ya no existen.
- [x] `php artisan route:list` no muestra ninguna ruta con `passkey` y `grep -ri passkey app routes config resources/js/components resources/js/pages` no devuelve resultados.
- [x] `/login`, `/user/confirm-password` y `/settings/security` no muestran ningún texto ni botón de passkey.
- [x] `package.json` no contiene `@laravel/passkeys` ni `vue-input-otp`.
- [x] `php artisan route:list` no muestra rutas `two-factor` y `/settings/security` solo ofrece cambiar la contraseña, sin sección de verificación en dos pasos.
- [x] El HTML de cualquier página incluye `<link rel="icon" href="/img/icon.svg">`, y `GET /favicon.ico` y `GET /apple-touch-icon.png` responden 200 con la imagen del isotipo de Ravsign (no el logo de Laravel).

---

## Decisions

- **Yes:** llevar las pantallas del artifact a rutas Inertia reales con datos de ejemplo desde controladores. Al agregar backend solo cambia el origen de los datos.
- **No:** un solo componente con navegación por estado como el prototipo. Habría que rehacerlo al conectar el backend.
- **Yes:** dividir el diseño en dos specs (02 y 03). La interacción del editor y la firma es grande y merece su propia rama y verificación.
- **Yes:** fuentes autohospedadas con `@fontsource`. Entran en `public/build`, no dependen de un CDN y funcionan en el hosting sin Node.
- **No:** Google Fonts por CDN. Añade una dependencia externa y datos personales a terceros por el navegador.
- **Yes:** solo modo claro y eliminar la pestaña Apariencia. El diseño no define modo oscuro y mantenerlo obligaría a inventar una paleta.
- **Yes:** quitar la verificación de correo por ahora. El correo está en `log` y nadie podría verificar; el diseño lleva del registro directo a Documentos. Se reactiva en la spec de correo.
- **Yes:** registro con nombre, correo y contraseña. Fortify solo guarda esos datos y el campo "Empresa" exigiría una migración.
- **No:** mostrar "Empresa" sin guardarla. Descartaría el dato sin avisar al usuario.
- **Yes:** rediseñar también los ajustes, aplicando el mismo tema. Se rediseñan visualmente, no se tocan sus rutas ni su lógica.
- **Yes:** la zona de subida solo muestra un aviso. No hay editor ni backend que reciba el archivo.
- **Yes:** las filas del listado no son clicables hasta SPEC 03, donde borradores abren el editor y el resto el modal de enlaces.
- **Yes:** "Probar el editor" apunta a `/login`. El editor solo existe tras iniciar sesión y llega en SPEC 03.
- **Yes:** `APP_NAME=Ravsign`, igual que el logo. Sustituye a `RavSign` definido en SPEC 01.
- **Yes:** eliminar passkeys por completo (decisión del usuario durante la implementación). No forman parte del diseño y añadían una superficie de autenticación extra sin uso.
- **Yes:** eliminar la verificación en dos pasos por ahora (decisión del usuario durante la implementación: "de momento tampoco va"). Se reintroduce, con su propia spec, si hace falta. Las columnas `two_factor_*` que ya existan en una base local quedan sin uso.
- **Yes:** favicon con el isotipo `icon.svg`. `favicon.ico` (32 y 48 px) y `apple-touch-icon.png` (180 px, fondo blanco) se generaron una sola vez desde el SVG, porque Safari y iOS no usan el favicon SVG.
- **Yes:** los textos de las pantallas van escritos directamente en español, sin librería de i18n. Es el único idioma del producto por ahora.
- **Yes:** breakpoint móvil de 760 px como en el diseño, usando un breakpoint personalizado de Tailwind en lugar de `md` (768 px).

---

## Risks

| Risk | Mitigation |
| --- | --- |
| Quitar `MustVerifyEmail` rompe tests y rutas del starter que lo asumen. | El paso 4 ajusta los tests. El criterio `php artisan test` pasa completo lo comprueba. |
| Quitar `Appearance` deja imports huérfanos (`useAppearance`) y rutas Wayfinder generadas. | `npm run types:check` y `npm run build` fallan si queda alguna referencia. |
| Restyle de los componentes shadcn-vue afecta a pantallas que no están en el diseño (cambiar contraseña, eliminar cuenta). | Se cambia solo por tokens de tema, no por marcado. Se comprueban a mano `/settings/profile` y `/settings/security`. |
| Fidelidad visual: el artifact usa estilos inline y el starter usa Tailwind y shadcn. | Los valores exactos (colores, tamaños, radios) están listados arriba y se comparan pantalla a pantalla con el artifact. |
| Dos specs comparten el tipo `DocumentItem` y `MockData`. | El tipo completo se define aquí para que SPEC 03 no lo reescriba. |

---

## What is **not** in this spec

- Editor de campos, pantalla de firma y modal de enlaces de firma (SPEC 03).
- Subir, guardar o firmar documentos de verdad, y cualquier tabla nueva.
- Verificación de correo, envío de correos y SMTP.
- Modo oscuro y páginas de Términos y Privacidad.
- Campo "Empresa" en el registro.

Cada uno de estos puntos, si se hace, va en su propia spec.
