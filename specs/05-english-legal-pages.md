# SPEC 05 — Traducción al inglés y páginas legales

> **Status:** Implementado
> **Depends on:** SPEC 02, SPEC 03, SPEC 04
> **Date:** 2026-09-30
> **Objective:** Traducir al inglés todo el texto visible de la aplicación (páginas Inertia/Vue y los tres correos transaccionales de Mailgun) y añadir dos páginas públicas nuevas, Términos de servicio y Política de privacidad, con el contenido del artifact de referencia.

---

## Por qué existe esta spec

SPEC 02 dejó la aplicación en español a propósito ("el resto de la app ya está en español"), y SPEC 04 dejó explícito que internacionalizar el correo a otro idioma quedaba fuera de alcance. Esta spec revierte esa decisión por pedido explícito del usuario: la app pasa a estar enteramente en inglés, sin selector de idioma ni sistema de i18n — es un reemplazo directo del texto en español por su versión en inglés en los mismos archivos.

El usuario compartió un artifact (prototipo visual) con el diseño de las páginas de Términos de servicio y Política de privacidad. Ese artifact es un mockup independiente (no usa Vue/Inertia/Tailwind del proyecto), así que esta spec toma solo su contenido y estructura (las 8 secciones de Términos y las 7 de Privacidad, el layout de dos pestañas con navegación lateral "On this page") y lo reconstruye con los componentes y el sistema de diseño reales de Ravsign, en inglés y con el dominio real (`ravsign.site`), no `ravsign.com` como aparecía en el prototipo.

Los mensajes de validación por defecto de Laravel (Fortify, reglas `required`, etc.) ya están en inglés — no se tocan, siguen fuera de alcance como en SPEC 02.

---

## Scope

**In:**

*Páginas legales (nuevas)*

- Dos rutas públicas (sin `auth`), `GET /terms` (`legal.terms`) y `GET /privacy` (`legal.privacy`), registradas con `Route::inertia(...)` en `routes/web.php`, igual que `home`. No requieren controlador propio: cada ruta pasa una prop `type: 'terms' | 'privacy'` al componente.
- Un solo componente `resources/js/pages/legal/Show.vue` que recibe `type` como prop y renderiza el layout compartido: header con el logo de Ravsign y un botón "← Back", dos pestañas ("Terms" / "Privacy") que navegan entre `/terms` y `/privacy`, una navegación lateral "On this page" (solo desktop) con los números y títulos de cada sección y scroll suave al hacer clic, y el artículo con el párrafo introductorio + las secciones numeradas.
- El contenido de cada página (título de sección + cuerpo) vive como dos constantes locales en el componente, `TERMS_SECTIONS` y `PRIVACY_SECTIONS`, con la traducción al inglés del contenido del artifact (texto exacto en la sección **Data model**).
- Pie de cada artículo: "If you have questions about this document, write to us at legal@ravsign.site."
- Encabezado de cada página: "Last updated: September 29, 2026" (misma fecha que trae el artifact).

*Enlaces a las páginas legales*

- Footer del landing (`Welcome.vue`): "Terms of Service" y "Privacy Policy", enlazando a `/terms` y `/privacy`.
- Formulario de registro (`auth/Register.vue`): el checkbox obligatorio pasa a decir "I accept the Terms of Service and Privacy Policy.", con esos dos términos como enlaces a `/terms` y `/privacy` (se abren en una pestaña nueva para no perder el formulario).
- `AppSidebar.vue`: debajo de `NavUser`, una línea pequeña "Terms · Privacy" con ambos enlaces, visible en todas las páginas que usan `AppLayout` (dashboard de documentos y settings).
- `documents/Editor.vue` y `sign/Show.vue` (pantallas de página completa, sin sidebar): una franja inferior angosta (~28px, oculta en móvil) con los mismos enlaces "Terms · Privacy", igual de discreta que el resto de la barra de estado de esas pantallas.

*Traducción al inglés — páginas y componentes Vue*

- `Welcome.vue` (landing completa: header, hero, "How it works", "Features", CTA, footer).
- `layouts/auth/AuthSplitLayout.vue` y las 6 páginas de `pages/auth/`: `Login.vue`, `Register.vue`, `ForgotPassword.vue`, `ResetPassword.vue`, `ConfirmPassword.vue`, `VerifyEmail.vue`.
- `pages/documents/Index.vue` (dashboard: título, botón "Upload document", zona de arrastrar y soltar, filtros "All/Drafts/Pending/Completed", cabecera de tabla, estado vacío) y `pages/documents/LinksModal.vue`.
- `pages/documents/Editor.vue`, `components/editor/SelectedFieldPanel.vue`, `components/editor/SignersPanel.vue`.
- `pages/sign/Show.vue` (incluye los textos ya agregados en el cambio anterior: "Firmar", "Finalizar firma", mensajes de estado) y `pages/sign/SignatureModal.vue`.
- `components/SignFieldBox.vue` y `lib/documents.ts` (`FIELD_LABELS`: Firma → Signature, Iniciales → Initials, Fecha → Date, Nombre → Name).
- `components/AppSidebar.vue`, `components/AppMobileHeader.vue`, `components/NavUser.vue`, `components/UserMenuContent.vue`, `components/DeleteUser.vue`.
- `pages/settings/Profile.vue`, `pages/settings/Security.vue`.
- Cualquier mensaje de error o `toast.error(...)` en texto plano dentro de estos archivos (ej. "No se pudo subir el documento." → "Could not upload the document.").

*Traducción al inglés — backend*

- `app/Http/Controllers/SignController.php`: los dos mensajes de `Inertia::flash('toast', ...)` en `finish()`.
- `app/Http/Requests/StoreDocumentRequest.php`, `StoreSignerRequest.php`, `SignFieldRequest.php`: los mensajes personalizados de `messages()`.
- `app/Notifications/VerifyEmailNotification.php`, `ResetPasswordNotification.php`, `SignerInviteNotification.php`: el `MailMessage` completo (asunto, saludo, líneas, botón de acción) en inglés.
- `resources/views/vendor/mail/html/message.blade.php` y `resources/views/vendor/mail/text/message.blade.php`: "© {año} Ravsign. Todos los derechos reservados." → "© {año} Ravsign. All rights reserved."
- `app/Http/Controllers/SignerController.php`: el mensaje 422 de `resendInvite()` ("Este firmante ya firmó."). No estaba en el inventario original de esta spec; se encontró durante la implementación con un grep final por acentos en `app/`.
- `resources/js/layouts/settings/Layout.vue`: nav "Perfil/Seguridad" y título "Ajustes". Tampoco estaba en el inventario original — sus palabras no llevan tilde, así que el primer grep por `[áéíóúñÁÉÍÓÚÑ]` no lo detectó.

**Out of scope (para specs futuras):**

- Selector de idioma o cualquier sistema de i18n (`vue-i18n` u otro): el texto queda fijo en inglés, sin alternar a español.
- Traducir los mensajes de validación por defecto de Laravel/Fortify (ya están en inglés).
- Traducir `app/Console/Commands/MakeSamplePdf.php` (comando de desarrollo, no es contenido visible en la web).
- Cambiar el contenido legal más allá de traducirlo y adaptar el dominio (no se agregan cláusulas nuevas ni se consulta a un abogado).
- Aceptación explícita registrada en base de datos de los términos por parte de cada usuario (el checkbox de registro sigue siendo solo de UI, como ya lo era en español).
- Traducir el nombre de archivos, rutas o nombres de variables internas (siguen en español donde ya lo estaban, ej. `DocumentStatus`, `SignField`).

---

## Data model

Esta spec no añade tablas ni columnas. Los dos arreglos de contenido legal son datos estáticos del frontend, no persistidos:

```ts
// resources/js/pages/legal/Show.vue
const TERMS_SECTIONS = [
  { title: 'Using the Service', body: 'You may use Ravsign to prepare documents, add signers, and send them a signing link. You are responsible for having the right to share the documents you upload and for making sure the signers you add are the correct people.' },
  { title: 'Your Account', body: 'You must provide a valid name and email address. Keep your password secret; actions taken from your account are considered to have been taken by you.' },
  { title: 'Electronic Signatures', body: 'Each signer receives a unique link. By drawing or uploading their signature at that link, they agree to electronically sign the document. Ravsign records the date, time, and link used for each signature.' },
  { title: 'Document Content', body: 'Your documents remain yours. You grant us only the permission needed to store them, show them to the signers you add, and generate the final signed copy.' },
  { title: 'Prohibited Uses', body: 'You may not use Ravsign to impersonate another person, forge signatures, distribute illegal content, or attempt to access other users’ documents.' },
  { title: 'Suspension and Termination', body: 'You may close your account at any time. We may suspend accounts that violate these terms, notifying you when possible.' },
  { title: 'Liability', body: 'Ravsign is provided as is. We are not a party to the agreements you sign and are not responsible for the content of your documents or their legal validity in any given jurisdiction.' },
  { title: 'Changes to These Terms', body: 'If we change these terms, we will notify you by email in advance. Continuing to use the service after the change means you accept it.' },
];

const PRIVACY_SECTIONS = [
  { title: 'Data We Collect', body: 'Your account name and email; the documents you upload; the names and initials of the signers you add; drawn or uploaded signatures; and technical data such as IP address, browser, and the date of each action.' },
  { title: 'How We Use It', body: 'To provide the service: show the document to each signer, record who signed and when, generate the final copy, and send you notices about the status of your documents.' },
  { title: 'Signatures', body: 'Each person’s signature image is used only in the fields assigned to them within the document they are signing. We do not reuse it in other documents unless they provide it again.' },
  { title: 'Who We Share It With', body: 'The signers of a document can see that document. We use hosting and email-delivery providers who process data on our behalf. We do not sell personal data.' },
  { title: 'Retention', body: 'We keep documents for as long as your account is active or until you delete them. The signature record is kept for as long as needed to prove the document’s validity.' },
  { title: 'Security', body: 'Documents are encrypted in transit and at rest. Each signing link is unique and grants access only to the corresponding document.' },
  { title: 'Your Rights', body: 'You can access, correct, export, or delete your data from your account or by writing to us. You can also object to specific processing or file a complaint with a data protection authority.' },
];
```

Conventions:

- Cada sección se numera automáticamente en el componente (`index + 1`), igual que en el artifact — no se guarda el número en el arreglo.
- El anchor de cada sección es `legal-{n}` (ej. `legal-3`), usado tanto por el enlace de "On this page" como por el `id` de la sección, para el scroll suave.

---

## Implementation plan

1. Añadir `Route::inertia('terms', 'legal/Show', ['type' => 'terms'])->name('legal.terms')` y el equivalente para `privacy`/`legal.privacy` en `routes/web.php`, fuera del grupo `auth`. Correr el comando de Wayfinder del proyecto para generar `resources/js/routes/legal/index.ts`. Verificar con `php artisan route:list` que ambas rutas responden sin login.
2. Crear `resources/js/pages/legal/Show.vue`: header con logo y botón "← Back" (vuelve a `document.referrer` o al landing), las dos pestañas Terms/Privacy, la nav lateral "On this page" (desktop), el artículo con introducción + `TERMS_SECTIONS`/`PRIVACY_SECTIONS` según `props.type`, y el pie de contacto. Verificar visitando `/terms` y `/privacy` en el navegador: ambas cargan, cambian de pestaña entre sí, y los enlaces de la nav lateral hacen scroll a la sección correcta.
3. Traducir `Welcome.vue` completo y añadir los enlaces "Terms of Service" / "Privacy Policy" en su footer, apuntando a las rutas nuevas. Verificar visitando `/`.
4. Traducir `layouts/auth/AuthSplitLayout.vue` y las 6 páginas de `pages/auth/`. En `Register.vue`, cambiar el texto del checkbox a "I accept the Terms of Service and Privacy Policy." con enlaces a `/terms` y `/privacy` en pestaña nueva. Verificar el flujo de registro, login, "olvidé mi contraseña" y restablecimiento de punta a punta en inglés.
5. Traducir `pages/documents/Index.vue`, `pages/documents/LinksModal.vue`, `components/AppSidebar.vue`, `components/AppMobileHeader.vue`, `components/NavUser.vue`, `components/UserMenuContent.vue`. Añadir el enlace "Terms · Privacy" bajo `NavUser` en `AppSidebar.vue`. Verificar el dashboard de documentos con al menos un documento en cada estado.
6. Traducir `pages/documents/Editor.vue`, `components/editor/SelectedFieldPanel.vue`, `components/editor/SignersPanel.vue`, `components/SignFieldBox.vue` y `FIELD_LABELS` en `lib/documents.ts`. Añadir la franja inferior "Terms · Privacy" en `Editor.vue`. Verificar subiendo un PDF, agregando firmantes y campos.
7. Traducir `pages/sign/Show.vue` y `pages/sign/SignatureModal.vue` (incluye los textos del cambio de firma múltiple/botón "Firmar" ya implementado: "Firmar" → "Sign", "Finalizar firma" → "Finish signing", "Ya firmaste este documento..." → "You already signed this document...", etc.). Añadir la franja "Terms · Privacy" igual que en el editor. Verificar el flujo de firma completo desde un enlace público.
8. Traducir `pages/settings/Profile.vue`, `pages/settings/Security.vue`, `components/DeleteUser.vue`. Verificar ambas pantallas de configuración.
9. Traducir en el backend: los dos flashes de `SignController::finish()`, los mensajes de `StoreDocumentRequest`, `StoreSignerRequest` y `SignFieldRequest`, el contenido de las tres notificaciones (`VerifyEmailNotification`, `ResetPasswordNotification`, `SignerInviteNotification`) y el pie de `resources/views/vendor/mail/html/message.blade.php` / `text/message.blade.php`. Verificar con `Notification::fake()` en tinker o revisando `storage/logs/laravel.log` que el asunto y el cuerpo de cada correo están en inglés.
10. Revisar y actualizar los tests de feature que aserten texto en español (`SignPageTest`, `DocumentsTest`, `DocumentEditorTest`, `SignFieldTest`, tests de auth/notificaciones) para que esperen el texto en inglés donde corresponda. Ejecutar `php artisan test`, `npm run types:check` y `npm run build`.
11. Verificación final de punta a punta: recorrer landing → registro (aceptando el checkbox con los enlaces legales) → verificación de correo → dashboard → subir documento → editor → enviar a firmar → abrir `/terms` y `/privacy` desde el footer del landing y desde el sidebar del dashboard → firmar como firmante público sin sesión iniciada, confirmando que todo el texto visible está en inglés y que el firmante se queda en la misma pantalla al finalizar (comportamiento ya implementado). Revisar `storage/logs/laravel.log` y la consola del navegador.

---

## Acceptance criteria

**Páginas legales**

- [x] `GET /terms` y `GET /privacy` responden 200 sin sesión iniciada.
- [x] `/terms` muestra las 8 secciones de `TERMS_SECTIONS` numeradas 1 a 8, con la pestaña "Terms" activa.
- [x] `/privacy` muestra las 7 secciones de `PRIVACY_SECTIONS` numeradas 1 a 7, con la pestaña "Privacy" activa.
- [x] Cambiar de pestaña en cualquiera de las dos páginas navega a la otra ruta sin perder el layout.
- [x] En desktop, hacer clic en un ítem de "On this page" hace scroll suave hasta esa sección.
- [x] El pie de cada página muestra "legal@ravsign.site" como correo de contacto.

**Enlaces**

- [x] El footer del landing (`/`) tiene enlaces a `/terms` y `/privacy`.
- [x] El formulario de registro muestra "I accept the Terms of Service and Privacy Policy." con ambos términos enlazados, y sigue siendo obligatorio marcar el checkbox para poder enviar el formulario.
- [x] El sidebar del dashboard (`/documents`) muestra "Terms · Privacy" con ambos enlaces.
- [x] El editor (`/documents/{document}/editor`) y la pantalla de firma (`/sign/{token}`) muestran la misma franja de enlaces.

**Traducción**

- [x] Ninguna de las páginas listadas en el Scope contiene texto en español visible para el usuario (verificación manual recorriendo cada pantalla).
- [x] Los tres correos transaccionales (verificación, restablecer contraseña, invitación a firmar) están en inglés, incluido el pie con "All rights reserved.".
- [x] Los mensajes de error de validación personalizados (`StoreDocumentRequest`, `StoreSignerRequest`, `SignFieldRequest`) están en inglés.
- [x] Los dos toasts de `SignController::finish()` están en inglés.

**Tests y build**

- [x] `php artisan test` pasa completo, incluidos los tests actualizados de este spec.
- [x] `npm run types:check` y `npm run build` terminan sin errores.

---

## Decisions

- **Yes:** reemplazo directo del texto en español por inglés en los mismos archivos, sin librería de i18n ni selector de idioma. El usuario pidió "todo lo visible en la web debe quedar en inglés" — un idioma final, no dos coexistiendo.
- **No:** un sistema de traducciones (`vue-i18n`, archivos de idioma de Laravel). Sería mucho más trabajo del pedido y no hay ningún requisito de mantener español en paralelo.
- **Yes:** los tres correos transaccionales de Mailgun (SPEC 04) también se traducen, por decisión explícita del usuario al preguntarle si el alcance incluía solo páginas web o también los correos.
- **No:** los mensajes de validación por defecto de Laravel/Fortify. Ya están en inglés y SPEC 02 ya los había dejado fuera de alcance; no hay nada que cambiar ahí.
- **Yes:** usar `ravsign.site` (el dominio real, ya configurado en SPEC 04 para Mailgun y el logo) en vez de `ravsign.com`, que aparecía en el artifact de referencia porque es un mockup genérico y no representa el dominio final del proyecto.
- **Yes:** un solo componente `legal/Show.vue` con una prop `type`, en vez de dos componentes separados (`Terms.vue` y `Privacy.vue`). Ambas páginas comparten layout, navegación y estilo casi por completo — como ya lo modela el propio artifact con una sola pantalla "Legal" con dos pestañas.
- **Yes:** rutas públicas sin `auth`, registradas con `Route::inertia(...)` igual que `home`, sin controlador propio. El contenido es estático y no depende de ningún modelo.
- **No:** guardar en base de datos la aceptación de los términos por parte de cada usuario. El checkbox de registro ya era solo de UI antes de esta spec (no crea una columna `terms_accepted_at` ni nada similar); cambiarlo es una decisión de producto aparte que no se pidió.
- **Yes:** agregar enlaces a las páginas legales también en el editor y en la pantalla de firma (pantallas de página completa sin sidebar), como una franja angosta al pie, además del landing, el registro y el sidebar del dashboard, por pedido explícito del usuario al preguntarle el alcance de los enlaces.
- **No:** traducir `app/Console/Commands/MakeSamplePdf.php`. Es una herramienta de desarrollo (genera un PDF de ejemplo por CLI), no contenido visible en la web.

---

## Risks

| Risk | Mitigation |
| --- | --- |
| Algún texto en español queda sin traducir por estar embebido en un `computed()` o en un string armado dinámicamente (ej. plurales "campo"/"campos") en vez de un literal fácil de encontrar con grep. | Recorrer manualmente cada pantalla del paso 11 además de grepear por caracteres acentuados (`[áéíóúñÁÉÍÓÚÑ]`) antes de dar la spec por terminada. |
| Los tests de feature existentes (`SignPageTest`, `DocumentsTest`, tests de notificaciones) aserten strings exactos en español y quedan rotos tras la traducción. | Paso 10 dedicado a revisar y actualizar esos tests; correr `php artisan test` antes de cerrar la spec. |
| El contenido legal traducido no es asesoría legal real: es una traducción del texto genérico del artifact, no una revisión de un abogado. | Aceptado explícitamente — el alcance de esta spec es traducir y adaptar el dominio, no redactar términos legales nuevos (ver Scope). |
| La franja de enlaces "Terms · Privacy" en el editor y en la pantalla de firma, ambas de altura fija (`h-dvh`), puede no caber sin recortar contenido en pantallas muy bajas (ej. móvil en landscape). | Diseñarla oculta en móvil (`hidden desk:flex` o equivalente) y de altura mínima (~28px) en desktop, igual que el resto de las barras de estado ya angostas de esas pantallas. |

---

## What is **not** in this spec

- Selector de idioma o cualquier sistema de i18n.
- Traducción de los mensajes de validación por defecto de Laravel/Fortify.
- Traducción de `app/Console/Commands/MakeSamplePdf.php`.
- Contenido legal redactado o revisado por un abogado.
- Registro en base de datos de la aceptación de los términos por parte del usuario.

Cada uno de estos puntos, si se hace, va en su propia spec.
