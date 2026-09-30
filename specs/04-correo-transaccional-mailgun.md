# SPEC 04 — Correo transaccional con Mailgun

> **Status:** Implementado
> **Depends on:** SPEC 03
> **Date:** 2026-09-30
> **Objective:** Configurar Mailgun como proveedor de correo activo y enviar, con `MailMessage` (sin plantillas Blade propias por correo), los tres correos transaccionales de Ravsign: verificación de registro, restablecimiento de contraseña y enlace de firma para cada firmante, todos con el layout de correo de Laravel personalizado con el logo y los colores de la marca.

---

## Por qué existe esta spec

SPEC 02 desactivó por completo la verificación de correo ("sin `MustVerifyEmail`, sin la feature de Fortify y sin middleware `verified`") y SPEC 03 dejó explícito que el envío de enlaces de firma es manual ("Envío de enlaces por correo y verificación de email" quedaban fuera de alcance, y el README dice "no se envían por correo: se copian a mano"). Esta spec cierra esos dos pendientes y añade el proveedor de correo real.

El usuario ya tiene una cuenta y un dominio de Mailgun configurados, con `MAILGUN_DOMAIN` y `MAILGUN_SECRET` puestos en su `.env` local. Esta spec dedica el código a que ese proveedor quede activo por defecto, y a que los tres correos usen `Illuminate\Notifications\Messages\MailMessage` instanciado dentro de cada clase de notificación (con `->line()`, `->action()`, etc.), en vez de una vista Blade propia por correo. El layout compartido de Laravel para ese tipo de correo (el que se publica con `vendor:publish --tag=laravel-mail`) es el que se deja en el repositorio, personalizado con el logo `https://ravsign.site/img/logo.svg`, los colores de la marca (`#1a3560`, `#1792bb`) y un pie con el nombre del sitio.

---

## Scope

**In:**

*Proveedor de correo*

- Paquete `symfony/mailgun-mailer` y `symfony/http-client` instalados vía Composer (son los que Laravel usa para el transporte `mailgun`).
- `config/mail.php`: mailer `mailgun` añadido a `mailers` (`'transport' => 'mailgun'`), con el dominio y las opciones que necesite `services.mailgun`. `MAIL_MAILER` pasa a `mailgun` por defecto en `.env.example` (queda documentado; el `.env` local del usuario ya tiene sus credenciales).
- `config/services.php`: bloque `mailgun` con `domain`, `secret` y `endpoint` desde `env()`.
- `.env.example` documenta `MAIL_MAILER=mailgun`, `MAILGUN_DOMAIN`, `MAILGUN_SECRET`, `MAILGUN_ENDPOINT` (por defecto `api.mailgun.net`, o `api.eu.mailgun.net` si el dominio es de la región UE — se documenta como comentario), y `MAIL_FROM_ADDRESS=no-reply@ravsign.site` / `MAIL_FROM_NAME="${APP_NAME}"`.
- El README documenta cómo obtener `MAILGUN_DOMAIN` y `MAILGUN_SECRET` del panel de Mailgun (Sending → Domain settings → API Keys) y que el entorno de tests sigue sin enviar correos reales.

*Layout de correo compartido*

- Se publican las vistas de correo de Laravel (`php artisan vendor:publish --tag=laravel-mail`) en `resources/views/vendor/mail/`, versión HTML y texto plano.
- `resources/views/vendor/mail/html/themes/default.css` se ajusta a la paleta de Ravsign: fondo `#f6f8fb`, texto `#1a3560`, botón de acción turquesa `#1792bb` (hover/active `#1380a5`), tipografía por defecto del tema (no se autohospedan fuentes dentro del correo: los clientes de correo no cargan `@font-face` de forma fiable).
- `resources/views/vendor/mail/html/header.blade.php` muestra el logo `<img src="https://ravsign.site/img/logo.svg" ... height="28">` centrado en vez del texto del nombre de la app, enlazado a `https://ravsign.site`.
- `resources/views/vendor/mail/html/footer.blade.php` (y su versión texto) muestran "© {año actual} Ravsign. Todos los derechos reservados." y un enlace a `https://ravsign.site`.
- Estas vistas son el único lugar con marcado de correo del proyecto: ninguna de las tres notificaciones crea una vista Blade propia, todas construyen su contenido con `MailMessage` (`->subject()`, `->greeting()`, `->line()`, `->action()`), que se renderiza con este layout compartido.

*Verificación de email (registro)*

- `App\Models\User` implementa `Illuminate\Contracts\Auth\MustVerifyEmail` y sobrescribe `sendEmailVerificationNotification()` para enviar `App\Notifications\VerifyEmailNotification` (propia, con texto en español vía `MailMessage`) en vez de la notificación por defecto de Laravel (en inglés).
- `config/fortify.php`: se añade `Features::emailVerification()` al arreglo `features`.
- `FortifyServiceProvider::configureViews()` añade `Fortify::verifyEmailView(...)` que renderiza una nueva página Inertia `auth/VerifyEmail.vue`, con el mismo layout de dos columnas que el resto de pantallas de auth (`AuthSplitLayout`), el texto "Revisa tu correo. Te enviamos un enlace de confirmación a {email}.", un botón "Reenviar correo" (hace `POST` a la ruta `verification.send` de Fortify) y "Cerrar sesión".
- `App\Providers\AppServiceProvider::boot()` registra `Event::listen(Registered::class, SendEmailVerificationNotification::class)` para que el correo se dispare al registrarse (usa el override de `sendEmailVerificationNotification()` del modelo, así que sale con el texto y el `MailMessage` propios).
- El middleware `verified` se añade al grupo de rutas protegidas de `/documents` en `routes/web.php` (junto a `auth`), de modo que un usuario registrado y sin verificar es redirigido a `verification.notice` al intentar entrar. `routes/settings.php` no se toca: un usuario sin verificar puede seguir viendo su perfil y cerrar sesión.
- El correo de verificación incluye un botón de acción ("Confirmar correo") que apunta a la URL firmada temporal que genera Laravel (`verification.verify`), como en el flujo estándar de Laravel.
- `DatabaseSeeder` fija `email_verified_at` explícitamente al crear el usuario de demostración, para que `test@example.com` pueda entrar a `/documents` sin pasos extra tras `migrate:fresh --seed`.

*Restablecimiento de contraseña*

- Ya existía (`Features::resetPasswords()` en Fortify), pero usaba la notificación `Illuminate\Auth\Notifications\ResetPassword` por defecto (texto en inglés). `App\Models\User` sobrescribe `sendPasswordResetNotification($token)` para enviar `App\Notifications\ResetPasswordNotification` propia (texto en español, mismo `MailMessage` + layout compartido), con el enlace `password.reset` con el `token` y el `email`.
- No cambia el flujo de páginas (`ForgotPassword.vue`, `ResetPassword.vue`) ni las rutas, solo el contenido y el remitente del correo.

*Enlace de firma para firmantes*

- `App\Notifications\SignerInviteNotification` (propia, `MailMessage`): asunto "Te invitaron a firmar '{nombre del documento}'", saludo con el nombre del firmante, una línea explicando quién envía el documento, un botón de acción "Confirmar y firmar" que enlaza a `/sign/{token}`, y una línea de aviso de que el enlace es personal e intransferible.
- `DocumentController::send()` (SPEC 03) envía este correo a cada firmante justo después de generar su `token`, dentro de la misma transacción de guardado (el envío del correo en sí queda fuera de la transacción de base de datos, pero se dispara inmediatamente después, antes de responder). Si el correo de un firmante falla, no revierte el envío del documento ni bloquea a los demás firmantes: se registra el error en el log y la respuesta sigue siendo exitosa (los enlaces siguen disponibles para copiar a mano desde el modal).
- Nueva ruta `POST /documents/{document}/signers/{signer}/resend-invite` (`signers.resend-invite`, `auth`, dueño, dentro de `Route::scopeBindings()`), que reenvía `SignerInviteNotification` al firmante. Solo tiene efecto si el documento está `pendiente` y el firmante no ha firmado (`signed_at` nulo); si el firmante ya firmó, responde 422 con "Este firmante ya firmó."
- `LinksModal.vue` añade un botón "Reenviar correo" en cada fila cuyo estado es `pendiente`, junto a "Copiar" y "Abrir enlace", con un aviso de éxito ("Correo reenviado a {nombre}") o error.

*Colas*

- Las tres notificaciones implementan `ShouldQueue`. Como `QUEUE_CONNECTION=sync` en desarrollo y en el hosting compartido (sin workers, documentado ya en el README), se siguen enviando de forma síncrona sin cambios de infraestructura; si en el futuro se activa una cola real, ya quedan listas para encolarse sin tocar código.

**Out of scope (para specs futuras):**

- Traducir al español el resto de textos de validación de Laravel (ya estaba fuera de alcance en SPEC 02).
- Caducidad o revocación de enlaces de firma, recordatorios automáticos o reenvíos programados.
- Plantillas de correo con Markdown de Laravel (`Mail::markdown`) o Mailables independientes de las notificaciones: todo se resuelve con `Notification` + `MailMessage`.
- Webhooks de Mailgun (rebotes, quejas, entregado) y cualquier panel de estado de envío dentro de la app.
- Cambiar el remitente por firmante o por dominio distinto de `ravsign.site`.
- Internacionalizar el contenido de los correos a otro idioma.
- Tests end-to-end con navegador para el nuevo botón "Reenviar correo".

---

## Data model

No se añaden columnas ni tablas nuevas. Se reutilizan `signers.token` y `documents.status` (SPEC 03) para decidir si un reenvío es válido.

`resources/js/types/documents.ts` no cambia de forma; `LinksModal.vue` solo añade una acción de UI sobre datos que ya existen (`SignLink`, `Signer`).

---

## Implementation plan

1. `composer require symfony/mailgun-mailer symfony/http-client`. Añadir el mailer `mailgun` a `config/mail.php` y el bloque `mailgun` (`domain`, `secret`, `endpoint`) a `config/services.php`. Actualizar `.env.example` con `MAIL_MAILER=mailgun`, `MAILGUN_DOMAIN`, `MAILGUN_SECRET`, `MAILGUN_ENDPOINT` y el remitente `no-reply@ravsign.site`. Verificar con `php artisan tinker` (`Mail::raw('prueba', fn ($m) => $m->to('...')->subject('prueba'))`) que sale un 200 de la API de Mailgun contra el `.env` local del usuario.
2. `php artisan vendor:publish --tag=laravel-mail`. Editar `resources/views/vendor/mail/html/themes/default.css` (colores de marca), `header.blade.php` (logo `https://ravsign.site/img/logo.svg`, enlazado) y `footer.blade.php` (nombre del sitio y año) en HTML y texto plano. Verificar generando un correo de prueba y revisándolo en un cliente de correo o en Mailtrap/el log.
3. Crear `App\Notifications\VerifyEmailNotification`, `App\Notifications\ResetPasswordNotification` y `App\Notifications\SignerInviteNotification`, cada una con `toMail()` devolviendo un `MailMessage` propio en español, y `ShouldQueue`. Sin vistas Blade adicionales.
4. `App\Models\User implements MustVerifyEmail`; sobrescribir `sendEmailVerificationNotification()` y `sendPasswordResetNotification($token)` para usar las notificaciones nuevas. Añadir `Features::emailVerification()` a `config/fortify.php`. Verificar con un test manual: registrar un usuario y confirmar que llega (o queda en el log/Mailgun) el correo de verificación en español con el logo.
5. `FortifyServiceProvider::configureViews()`: añadir `Fortify::verifyEmailView(...)`. Crear `resources/js/pages/auth/VerifyEmail.vue` con el layout de dos columnas existente, mensaje, botón "Reenviar correo" (`POST verification.send`) y "Cerrar sesión". Añadir `Event::listen(Registered::class, SendEmailVerificationNotification::class)` en `AppServiceProvider::boot()`. Añadir `verified` al grupo `auth` de `/documents` en `routes/web.php`. Fijar `email_verified_at` en `DatabaseSeeder`. Verificar: un usuario recién registrado ve `auth/VerifyEmail.vue` al intentar entrar a `/documents`, y tras hacer clic en el enlace del correo entra sin problema.
6. Actualizar `DocumentController::send()` (SPEC 03) para notificar a cada firmante con `SignerInviteNotification` tras generar su token, con el error de envío capturado y registrado en el log sin interrumpir la respuesta. Verificar enviando un documento y comprobando que cada firmante recibe el correo con el botón "Confirmar y firmar" apuntando a `/sign/{token}`.
7. Crear `SignerController::resendInvite()` y la ruta `POST /documents/{document}/signers/{signer}/resend-invite`. Añadir el botón "Reenviar correo" en `LinksModal.vue` para las filas `pendiente`, con aviso de éxito o error. Verificar el reenvío y el 422 al intentar reenviar a un firmante ya firmado.
8. Escribir/actualizar tests de feature: verificación de email obligatoria antes de entrar a `/documents`, reenvío de verificación, contenido y destinatario del correo de restablecimiento de contraseña (`Notification::fake()`), envío del correo de invitación al enviar un documento (`Notification::fake()` con `assertSentTo`), 422 al reenviar a un firmante ya firmado, y que un documento se sigue enviando aunque el correo de un firmante falle (mock del canal de correo lanzando una excepción). Ejecutar `php artisan test`, `npm run types:check` y `npm run build`.
9. Verificación final de punta a punta: `php artisan serve` (sin `npm run dev`), registrar un usuario nuevo, verificar el correo, iniciar sesión, subir un documento, agregar 2 firmantes, enviarlo y comprobar que llegan los dos correos con el botón de confirmación, hacer clic en uno para llegar a `/sign/{token}`, probar "Reenviar correo" y "Olvidé mi contraseña" de punta a punta. Revisar `storage/logs/laravel.log` y la consola del navegador. Actualizar el README (sección "Configuración por defecto": el correo deja de estar en `log`, pasa a `mailgun`; documentar las variables de Mailgun y el nuevo botón de reenvío).

---

## Acceptance criteria

**Proveedor de correo**

- [ ] Con las credenciales de Mailgun del `.env` local, `Mail::raw(...)->send()` entrega el correo (verificable en el panel de Mailgun o en la bandeja de destino).
- [ ] `.env.example` documenta `MAIL_MAILER=mailgun`, `MAILGUN_DOMAIN`, `MAILGUN_SECRET`, `MAILGUN_ENDPOINT` y `MAIL_FROM_ADDRESS=no-reply@ravsign.site`.
- [ ] Los tests (`MAIL_MAILER` no configurado o `array`/`log` en `phpunit.xml`) no intentan pegarle a la API real de Mailgun.

**Layout**

- [ ] Cualquier correo enviado con `MailMessage` (los tres tipos) muestra el logo `https://ravsign.site/img/logo.svg` en la cabecera, el botón de acción en turquesa `#1792bb` y el pie con "Ravsign" y el año actual.
- [ ] No existe ninguna vista Blade de correo propia del proyecto fuera de `resources/views/vendor/mail/`; las tres notificaciones construyen su contenido solo con métodos de `MailMessage`.

**Verificación de email**

- [ ] Registrar un usuario nuevo dispara `App\Notifications\VerifyEmailNotification` (verificable con `Notification::fake()` en el test) con el texto en español y un botón "Confirmar correo".
- [ ] Un usuario autenticado sin verificar que visita `/documents` es redirigido a la pantalla de verificación (`auth/VerifyEmail.vue`), no a `/documents`.
- [ ] Al pulsar "Reenviar correo" en esa pantalla se envía otra vez la notificación.
- [ ] Al abrir el enlace firmado del correo, el usuario queda verificado y `/documents` responde 200.
- [ ] Tras `php artisan migrate:fresh --seed`, `test@example.com` entra directo a `/documents` sin pantalla de verificación.
- [ ] `/settings/profile` responde 200 para un usuario autenticado sin verificar.

**Restablecimiento de contraseña**

- [ ] Pedir "Olvidé mi contraseña" dispara `App\Notifications\ResetPasswordNotification` (no la de Laravel por defecto) con el enlace correcto (`email` y `token`) y el mismo logo y colores.
- [ ] El flujo completo (pedir enlace, abrirlo, poner contraseña nueva, iniciar sesión) sigue funcionando igual que antes de esta spec.

**Firmantes**

- [ ] "Enviar para firma" con 2 firmantes dispara `SignerInviteNotification` a cada uno (verificable con `Notification::fake()` y `assertSentTo`), con un botón "Confirmar y firmar" que apunta a `/sign/{token}` de ese firmante.
- [ ] Si el envío de correo a un firmante lanza una excepción, "Enviar para firma" igual completa (estado `pendiente`, tokens generados, modal de enlaces abierto) y el error queda en `storage/logs/laravel.log`.
- [ ] `POST /documents/{document}/signers/{signer}/resend-invite` reenvía el correo a un firmante `pendiente` y responde 422 con "Este firmante ya firmó." si `signed_at` no es nulo.
- [ ] En el modal de enlaces, cada fila `pendiente` tiene un botón "Reenviar correo" que muestra un aviso de éxito al pulsarlo.

**Tests y build**

- [ ] `php artisan test` pasa completo, incluidos los tests nuevos de este spec.
- [ ] `npm run types:check` y `npm run build` terminan sin errores.

**Flujo completo**

- [ ] Con una base recién migrada: registrar un usuario, verificar el correo, subir un documento, agregar 2 firmantes, enviarlo, recibir (o ver en el log/Mailgun) el correo de cada firmante, abrir uno con el botón "Confirmar y firmar", firmar y finalizar, sin errores en el log ni en la consola del navegador.

---

## Decisions

- **Yes:** Mailgun como único proveedor de correo de esta spec (paquete `symfony/mailgun-mailer`), por pedido explícito del usuario, que ya tiene la cuenta y el dominio configurados.
- **No:** dejar `MAIL_MAILER=log` por defecto en `.env.example`. El pedido explícito del usuario es "dejar el envío activo", así que el valor por defecto documentado pasa a `mailgun`.
- **Yes:** las tres notificaciones (verificación, restablecimiento, invitación a firmar) construyen su correo con `Illuminate\Notifications\Messages\MailMessage` dentro de la clase de notificación, sin una vista Blade propia por correo, por pedido explícito del usuario. El único marcado de correo del repositorio es el layout compartido publicado en `resources/views/vendor/mail/`.
- **Yes:** publicar y personalizar el layout de correo de Laravel (`vendor:publish --tag=laravel-mail`) en vez de escribir un layout desde cero. Es el mecanismo estándar que ya sabe renderizar cualquier `MailMessage`, y es donde el usuario pidió dejar el logo y los colores.
- **Yes:** reactivar la verificación de email como bloqueante (`MustVerifyEmail` + middleware `verified` en `/documents`), revirtiendo la decisión de SPEC 02 de desactivarla del todo. El usuario pidió explícitamente que "se envíen los correos de registro para confirmar email" y, al preguntarle, eligió que bloquee el acceso hasta confirmar.
- **No:** aplicar `verified` a `routes/settings.php`. Un usuario sin verificar puede seguir viendo su perfil y cerrando sesión; solo `/documents` (el producto) queda bloqueado.
- **Yes:** sobrescribir `sendEmailVerificationNotification()` y `sendPasswordResetNotification()` en `User` para usar notificaciones propias en español, en vez de dejar las de Laravel por defecto (en inglés). El resto de la app ya está en español (SPEC 02).
- **Yes:** el correo de firmante se envía automáticamente al pulsar "Enviar para firma", más un botón de reenvío manual en el modal de enlaces, por decisión explícita del usuario (además de seguir permitiendo copiar el enlace a mano, que ya existía en SPEC 03).
- **Yes:** un fallo al enviar el correo de un firmante no revierte ni bloquea el envío del documento; solo queda registrado en el log. Un problema de entrega de un correo no debe impedir que el resto de firmantes reciban su enlace ni que el dueño pueda copiarlo a mano.
- **Yes:** las notificaciones implementan `ShouldQueue`, aunque `QUEUE_CONNECTION=sync` las siga despachando en el momento. Deja el código listo para una cola real sin tocarlo de nuevo.
- **No:** webhooks de Mailgun para rastrear entregas o rebotes. No lo pidió el usuario y añade una superficie nueva (endpoint público, verificación de firma) que no aporta al pedido actual.

---

## Risks

| Risk | Mitigation |
| --- | --- |
| Las credenciales de Mailgun del `.env` local del usuario no coinciden con lo que espera `config/services.php` (región UE vs. US, dominio sandbox vs. verificado). | Documentar `MAILGUN_ENDPOINT` como variable explícita en `.env.example` y probar un envío real en el paso 1 antes de seguir. |
| Reactivar `MustVerifyEmail` rompe tests existentes que asumen acceso directo a `/documents` tras registrarse. | Revisar y actualizar `RegistrationTest.php` y cualquier test de `DocumentsTest.php`/`DocumentEditorTest.php` que dependa de eso; usar el estado `unverified()` de la factory solo donde el test lo necesite explícitamente. |
| El correo a un firmante con dirección inválida o inexistente hace fallar toda la petición de "Enviar para firma". | Capturar la excepción de envío por firmante dentro de un `try/catch` individual, sin que interrumpa el bucle ni la respuesta. |
| El logo SVG remoto (`https://ravsign.site/img/logo.svg`) no carga en algunos clientes de correo que bloquean imágenes externas por defecto. | Es una limitación conocida y aceptada del correo HTML; el `alt="Ravsign"` del `<img>` deja el nombre visible aunque la imagen no cargue. |
| Clientes de correo (Outlook, Gmail) ignoran o recortan el CSS del tema si se personaliza de más. | Mantener los cambios de `default.css` dentro de las variables que el tema ya expone (colores, no estructura), para no romper la compatibilidad que Laravel ya probó. |
| Un test que llama de verdad a `Notification::send` sin `Notification::fake()` intenta pegarle a la API de Mailgun durante `php artisan test`. | `phpunit.xml` fuerza `MAIL_MAILER=array` (o similar) en el entorno de tests, y los tests de correo usan `Notification::fake()`. |

---

## What is **not** in this spec

- Traducción del resto de mensajes de validación de Laravel.
- Caducidad, revocación o recordatorios automáticos de enlaces de firma.
- Webhooks de Mailgun o panel de estado de entrega dentro de la app.
- Cambiar el remitente por firmante o dominio.
- Tests con navegador.

Cada uno de estos puntos, si se hace, va en su propia spec.
