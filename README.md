# RavSign

App para firmar documentos: un usuario logueado sube documentos y define posiciones de firma. Otra persona, mediante un link, firma en esas posiciones subiendo una imagen o dibujando en un canvas.

Stack: Laravel 13, Vue 3 + Inertia, Tailwind, MySQL. PDF en servidor con `setasign/fpdi`, visor con `pdfjs-dist` y firma en canvas con `signature_pad`.

## Requisitos

- PHP 8.4 con las extensiones `gd` (convierte y valida las firmas), `zlib` (PDF y PNG con transparencia), `fileinfo`, `mbstring`, `zip`, `intl`, `bcmath`, `dom` y `pdo_mysql`.
- `upload_max_filesize` y `post_max_size` de PHP de al menos 12 MB: la app admite PDF de hasta 10 MB y 50 páginas.
- Composer 2.
- Node 22.18 o superior y npm (solo en local, el hosting no tiene Node).
- MySQL. Los tests usan SQLite en memoria (`pdo_sqlite`), así que no tocan tu base.

## Instalación local

```bash
git clone <repositorio> ravsign
cd ravsign
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Crea la base de datos `ravsign` en MySQL y ajusta `DB_HOST`, `DB_PORT`, `DB_USERNAME` y `DB_PASSWORD` en `.env`. Para que el correo salga de verdad (confirmación de registro, restablecer contraseña y enlaces de firma), completa también `MAILGUN_DOMAIN` y `MAILGUN_SECRET` — ver [Correo](#correo). Luego:

```bash
php artisan migrate
php artisan storage:link
```

Para desarrollar con recarga de Vue, ejecuta `npm run dev` junto con `php artisan serve`. Sin `npm run dev`, la app usa el build de `public/build`.

`php artisan storage:link` solo hace falta para el disco público del starter: los documentos, las firmas y los PDF firmados **no** se sirven desde `public/storage`.

## Datos de demostración

```bash
php artisan migrate:fresh --seed
```

Crea el usuario `test@example.com` (contraseña `password`, correo ya confirmado) con cuatro documentos de ejemplo: dos borradores, uno pendiente de firma y uno completado con su PDF firmado. Un usuario recién registrado empieza sin documentos, debe confirmar su correo antes de entrar a Documentos, y luego puede subir su primer PDF.

El PDF de ejemplo (`public/samples/acuerdo-servicios.pdf`) se genera con `php artisan app:make-sample-pdf`; ya está commiteado y el seeder lo usa.

`migrate:fresh` vacía la base de datos, pero no los archivos de `storage/app/private`. Si repites el seed muchas veces, puedes borrar a mano `documents/`, `signatures/` y `signed/` dentro de esa carpeta.

## Cómo funciona

1. La persona se registra y confirma su correo (correo de verificación, ver [Correo](#correo)); sin confirmar no puede entrar a Documentos.
2. Sube un PDF en **Documentos** (máx. 10 MB y 50 páginas). Queda como *borrador*.
3. En el editor agrega firmantes (hasta 4) y arrastra campos de firma sobre las páginas. Los campos se colocan en una rejilla de 8 puntos y se guardan como porcentaje de la página.
4. **Enviar para firma** genera un enlace por firmante, le envía un correo con un botón "Confirmar y firmar" a cada uno, y pasa el documento a *pendiente de firma*. Los enlaces también se pueden copiar a mano o reenviar por correo desde el modal de enlaces.
5. Cada firmante abre su enlace `/sign/{token}` (sin cuenta), firma sus campos dibujando o subiendo una imagen y pulsa **Finalizar firma**.
6. Cuando firma el último, el documento pasa a *completado* y se genera el PDF firmado, que el dueño descarga desde el modal de enlaces.

Los archivos van en el disco privado `storage/app/private`: `documents/` (originales), `signatures/` (firmas PNG) y `signed/` (PDF firmados). Para cambiar a S3 u otro disco haría falta ajustar `FILESYSTEM_DISK`; esa migración no está hecha.

## Correo

El correo lo envía [Mailgun](https://www.mailgun.com/) (`symfony/mailgun-mailer`). En `.env`:

```
MAIL_MAILER=mailgun
MAILGUN_DOMAIN=tu-dominio-verificado.com
MAILGUN_SECRET=key-...
MAILGUN_ENDPOINT=api.mailgun.net   # o api.eu.mailgun.net si el dominio es de la región UE
```

`MAILGUN_DOMAIN` y `MAILGUN_SECRET` salen del panel de Mailgun: **Sending → Domain settings** (el dominio verificado) y **API Keys** (la Private API key). Sin estas variables el envío falla; en local puedes volver a `MAIL_MAILER=log` para no depender de Mailgun mientras desarrollas.

Se envían tres correos, todos con `MailMessage` sobre el layout de correo de Laravel (`resources/views/vendor/mail/`, con el logo y los colores de Ravsign, sin plantillas propias por correo):

- **Confirmación de registro**: se envía al registrarse. Sin confirmar, `/documents` redirige a la pantalla de verificación, que tiene un botón para reenviarlo.
- **Restablecer contraseña**: se envía desde "¿Olvidaste tu contraseña?".
- **Invitación a firmar**: se envía a cada firmante al pulsar "Enviar para firma", con un botón "Confirmar y firmar" hacia su enlace. Si el envío a un firmante falla, no bloquea el envío del documento ni al resto de firmantes (queda en `storage/logs/laravel.log`); desde el modal de enlaces se puede reenviar a mano con "Reenviar correo".

## Límites conocidos

- Los enlaces de firma no caducan ni se pueden revocar.
- La versión libre de FPDI solo lee PDF hasta la versión 1.4; un PDF más moderno se rechaza al subirlo con un mensaje claro.
- Las páginas con rotación (`/Rotate`) no están cubiertas al estampar las firmas.
- Un firmante sin cuenta que pulsa **Salir** llega a `/documents`, que lo manda a iniciar sesión.

## Tests

```bash
php artisan test        # 127 tests, incluido el flujo completo de firma
npm run types:check
```

## Frontend: el build se sube a git

El hosting no tiene Node, así que `public/build` **se commitea**. Antes de cada deploy:

```bash
npm run build
git add public/build
git commit -m "Build de frontend"
```

Si olvidas este paso, el servidor mostrará el frontend anterior.

## Deploy en el hosting compartido

Requisitos del panel: PHP 8.4 seleccionado y `symlink` fuera de `disable_functions` (o crear el enlace a mano con `ln -s`).

Por SSH, en la carpeta del proyecto:

```bash
git pull
composer install --no-dev -o
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

La primera vez, además:

```bash
cp .env.example .env
php artisan key:generate
php artisan storage:link      # o: ln -s ../storage/app/public public/storage
```

Edita `.env` en el servidor con `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` y las credenciales de MySQL. El dominio debe apuntar a la carpeta `public/`.

## Configuración por defecto

- Sesión y caché en `database`, colas en `sync` (sin workers), correo con Mailgun (ver [Correo](#correo)).
- Los documentos se guardan en el disco privado `storage/app/private` y se sirven mediante controlador, nunca por URL directa.
- `vendor/`, `node_modules/` y `.env` no se suben a git.

## Specs

Las especificaciones del proyecto están en `specs/`.
