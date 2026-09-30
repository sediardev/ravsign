# RavSign

App para firmar documentos: un usuario logueado sube documentos y define posiciones de firma. Otra persona, mediante un link, firma en esas posiciones subiendo una imagen o dibujando en un canvas.

Stack: Laravel 13, Vue 3 + Inertia, Tailwind, MySQL. PDF en servidor con `setasign/fpdi`, visor con `pdfjs-dist` y firma en canvas con `signature_pad`.

## Requisitos

- PHP 8.4 con las extensiones `gd`, `fileinfo`, `mbstring`, `zip`, `intl`, `bcmath`, `dom` y `pdo_mysql`.
- Composer 2.
- Node 22.18 o superior y npm (solo en local, el hosting no tiene Node).
- MySQL.

## Instalación local

```bash
git clone <repositorio> ravsign
cd ravsign
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Crea la base de datos `ravsign` en MySQL y ajusta `DB_HOST`, `DB_PORT`, `DB_USERNAME` y `DB_PASSWORD` en `.env`. Luego:

```bash
php artisan migrate
php artisan storage:link
```

Para desarrollar con recarga de Vue, ejecuta `npm run dev` junto con `php artisan serve`. Sin `npm run dev`, la app usa el build de `public/build`.

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

- Sesión y caché en `database`, colas en `sync` (sin workers), correo en `log`.
- Los documentos se guardan en el disco privado `storage/app/private` y se sirven mediante controlador, nunca por URL directa.
- `vendor/`, `node_modules/` y `.env` no se suben a git.

## Specs

Las especificaciones del proyecto están en `specs/`.
