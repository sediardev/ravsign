# SPEC 03 — Modelos, editor de campos y firma sobre PDF

> **Status:** Implementado
> **Depends on:** SPEC 02
> **Date:** 2026-09-29
> **Objective:** Construir el flujo completo y funcional de Ravsign: modelos y migraciones de base de datos, subida de PDF a almacenamiento local, editor de campos de firma, modal de enlaces, pantalla de firma con dibujo o imagen y PDF final firmado, todo persistido y renderizado con pdf.js.

---

## Por qué existe esta spec

Es la segunda mitad del diseño del artifact (`https://claude.ai/artifact/3uiQ7uzUKAkkUA1BYz4165`) y contiene toda la interacción: arrastrar campos sobre el documento, asignarlos a firmantes y firmar. El prototipo simulaba el documento con HTML y guardaba el estado en memoria. Aquí se usa un PDF real renderizado con `pdfjs-dist` y un backend real, para validar desde ya el posicionamiento de campos sobre páginas reales y el estampado de la firma, que son las partes técnicas más delicadas del producto.

Esta spec incluye todo lo necesario para que el producto funcione de extremo a extremo: modelos Eloquent, migraciones, subida de PDF, tokens de firma, persistencia de campos y firmas, y generación del PDF firmado. Los archivos se guardan en almacenamiento local (disco `local`, `storage/app/private`). Pasar a S3 u otro disco queda para una spec futura y solo requiere cambiar `FILESYSTEM_DISK`.

Convenciones de coordenadas (iguales a las del artifact):

- Tamaño base de página: 612 × 792 puntos (Letter). Para PDF subidos de otro tamaño, las posiciones en porcentaje siguen valiendo y el tamaño en pantalla se calcula con la proporción real de cada página.
- Posición de un campo: `x` e `y` en porcentaje de la página, origen arriba a la izquierda, `page` base 0.
- Tamaños de campo en puntos, por defecto al crear uno nuevo: firma 176 × 58, iniciales 92 × 58, fecha 132 × 36, nombre 176 × 36. El ancho y el alto se guardan por campo y se pueden editar después (ver "Ajuste de tamaño" más abajo), así que dos campos del mismo tipo pueden acabar con tamaños distintos.
- Ajuste a rejilla de 8 puntos al soltar un campo.
- En móvil la página se escala a `min(1, (ancho de ventana − 24) / 612)`.
- En escritorio, un control de zoom flotante (alejar / porcentaje / acercar) en la esquina inferior derecha del editor permite acercar el documento entre 50 % y 250 % en pasos de 10 %, sobre el 100 % real; el porcentaje también restablece el zoom al pulsarlo. Sirve para ver y colocar un campo con más precisión.
- Colores de firmantes en orden: `#1792bb`, `#7a4fc9`, `#d27a1f`, `#2f9e6b`.

---

## Scope

**In:**

*Base de datos y modelos*

- Migraciones y modelos Eloquent `Document`, `Signer` y `SignField`, con relaciones, casts y factories, y enums PHP `DocumentStatus` y `FieldType`.
- Relación `User hasMany Document`.
- `DocumentSeeder` que crea los 4 documentos de ejemplo del artifact (con el PDF de ejemplo copiado al disco local) para el usuario de demostración, de modo que se pueda probar sin subir nada. `MockData` deja de usarse en el listado.

*Almacenamiento local*

- Los PDF originales, los PDF firmados y las imágenes de firma se guardan en el disco `local` (`storage/app/private`), nunca en `public`.
- Rutas con control de acceso que sirven los archivos: el dueño del documento (sesión) o el firmante (token).

*PDF y subida*

- Subida real de PDF desde `/documents` (botón "Subir documento" y zona de arrastre): valida tipo `application/pdf`, tamaño máximo 10 MB y máximo 50 páginas, crea el documento en `borrador` y abre el editor. Sustituye al aviso de SPEC 02.
- PDF de ejemplo de 2 páginas (Letter) con el "Acuerdo de prestación de servicios", generado con FPDF y guardado en `public/samples/acuerdo-servicios.pdf` con el comando Artisan `app:make-sample-pdf`.
- Componente Vue que renderiza cada página del PDF con `pdfjs-dist` en un `canvas` y superpone los campos en porcentajes.

*Editor* (`/documents/{document}/editor`, requiere sesión y ser dueño)

- Cabecera con "← Documentos", nombre del documento, subtítulo con estado y número de firmantes, pasos "1 Preparar / 2 Firmar" y botón "Enviar para firma".
- Panel de firmantes: lista, alta con nombre, correo y siglas (máximo 4, por defecto las iniciales del nombre), baja con "×" y selección del firmante activo. Alta y baja se guardan en la base de datos.
- Paleta con el campo "Firma" que se arrastra al documento, con fantasma mientras se arrastra.
- Mover campos ya colocados entre páginas, seleccionar, reasignar a otro firmante con un selector, eliminar con botón y con las teclas Suprimir o Retroceso. Cada cambio se guarda en el servidor.
- Con un campo seleccionado, editar su ancho y su alto de dos formas: escribiendo el valor en puntos en dos campos numéricos ("Ancho (pt)", "Alto (pt)"), o arrastrando el asa circular de su esquina inferior derecha directamente sobre el documento. Ambas vías están acotadas entre 24-400 pt de ancho y 16-200 pt de alto, y sin salirse de la página. La firma estampada se ajusta ("contain") al nuevo tamaño sin deformarse, dejando espacio vacío si la proporción no coincide exactamente.
- Resumen "N campos en el documento".
- Variante móvil: firmantes en hoja inferior, paleta y selección en barra inferior.
- Aviso "Agrega un firmante primero" y "Agrega al menos un campo al documento" cuando corresponda.
- Solo se pueden editar firmantes y campos mientras el documento está en `borrador`.

*Envío y enlaces*

- "Enviar para firma": valida en el servidor, genera el token de cada firmante, pasa el documento a `pendiente` y abre el modal de enlaces con "Enlaces generados para N firmantes".
- Modal "Enlaces de firma" en `/documents`: una fila por firmante con siglas, nombre, correo, URL, estado, "Copiar" (portapapeles y aviso) y "Abrir enlace".
- Filas del listado clicables: un borrador abre el editor; un documento pendiente o completado abre el modal de enlaces.
- El listado lee los documentos del usuario desde la base de datos, con filtros y contadores.

*Firma* (`/sign/{token}`, pública, sin sesión)

- Pantalla con "Firmando como {nombre}", "Finalizar firma", "Salir", "Tu enlace de firma", campos propios resaltados y ajenos atenuados. El PDF se sirve por una ruta con el mismo token.
- Modal de firma con pestañas "Dibujar" (canvas con `signature_pad`, botón "Limpiar") y "Subir imagen" (PNG o JPG, vista previa, "Haz clic para cambiar la imagen"), texto de consentimiento y botones "Cancelar" y "Adoptar y firmar".
- Recorte automático del trazo dibujado y reutilización de la firma adoptada por el mismo firmante en sus otros campos.
- La firma adoptada se guarda como PNG en el disco local y se asocia al campo.
- "Finalizar firma": valida en el servidor que todos los campos propios estén firmados, marca al firmante como firmado (`signed_at`) y pasa el documento a `completado` cuando firmaron todos, con los avisos del artifact.
- Variante móvil con barra inferior y sugerencia "Toca cada campo resaltado para firmar."

*PDF final*

- Al completarse el documento se genera con FPDI el PDF firmado (estampa cada imagen de firma en la posición del campo) y se guarda en el disco local.
- Descarga del PDF firmado desde el modal de enlaces de un documento completado (solo el dueño).

*Identificadores*

- `documents`, `signers` y `sign_fields` tienen, además del `id` incremental interno (solo para claves foráneas), una columna `uuid` única que es la que se expone: rutas (`/documents/{uuid}/editor`, etc.), respuestas JSON y props de Inertia. El `id` numérico nunca sale del servidor.
- El enlace de firma de cada firmante usa su propio identificador (`token`), distinto del `uuid` del firmante: un UUID v4 (36 caracteres) generado con `Str::uuid()` al enviar el documento, más corto que el `Str::random(64)` de la versión anterior.

*Otros*

- Cambiar el botón "Probar el editor" de la landing para que apunte a `/documents` (sin sesión, `auth` lo manda a `/login`).
- Tests de feature del backend (ver criterios).
- Build recompilado.

**Out of scope (para specs futuras):**

- Almacenamiento en la nube (S3) y cualquier disco distinto de `local`.
- Envío de enlaces por correo y verificación de email.
- Expiración y revocación de enlaces, auditoría, sello de tiempo y certificado de firma.
- Campos de iniciales, fecha y nombre en la paleta (el artifact solo expone "Firma"). Sí se renderizan y estampan si existen en los datos.
- Eliminar o renombrar documentos, reenviar o reabrir un documento ya enviado.
- Zoom, miniaturas y navegación entre páginas del PDF más allá del scroll.
- Gestos táctiles avanzados (pellizcar para hacer zoom).
- Tests end-to-end con navegador.

---

## Data model

Se reutilizan los tipos TypeScript `Signer`, `SignField`, `DocumentItem`, `DocumentStatus` y `FieldType` de SPEC 02 (`resources/js/types/documents.ts`). Ahora los produce el servidor mediante API Resources en lugar de `MockData`. Se añaden:

```ts
// resources/js/types/documents.ts
export interface SignLink {
    signerId: string;
    url: string; // absoluta, '/sign/{token}'
    token: string; // 64 caracteres
    status: 'pendiente' | 'firmado';
}
```

`DocumentItem` gana `pages: number` y `links: SignLink[]` (vacío en borrador). Todos los ids (`DocumentItem.id`, `Signer.id`, `SignField.id`, `SignLink.signerId`) son el `uuid` del recurso, como string. `SignField` gana `width: number` y `height: number` (puntos, editables).

### Migraciones

`documents`

| Columna | Tipo | Notas |
| --- | --- | --- |
| `id` | bigint PK | interno, solo para claves foráneas |
| `uuid` | uuid, unique | identificador público del documento; usado en rutas y respuestas |
| `user_id` | FK `users`, cascade | dueño |
| `name` | string | nombre original del archivo |
| `original_path` | string | `documents/{uuid}.pdf` en el disco `local` |
| `signed_path` | string null | `signed/{uuid}.pdf`, solo cuando está completado |
| `pages` | unsigned smallint | número de páginas |
| `status` | string, default `borrador` | `borrador`, `pendiente`, `completado` |
| `sent_at`, `completed_at` | timestamp null | |
| `created_at`, `updated_at` | timestamps | |

`signers`

| Columna | Tipo | Notas |
| --- | --- | --- |
| `id` | bigint PK | interno, solo para claves foráneas |
| `uuid` | uuid, unique | identificador público del firmante como recurso (editor, reasignación) |
| `document_id` | FK `documents`, cascade | |
| `name`, `email` | string | |
| `siglas` | string(4) | |
| `color` | string(7) | uno de `#1792bb`, `#7a4fc9`, `#d27a1f`, `#2f9e6b` |
| `position` | unsigned tinyint | orden, define el color |
| `token` | string(36) null, unique | credencial del enlace de firma, distinta de `uuid`; se genera al enviar con `Str::uuid()` |
| `signed_at` | timestamp null | |
| `created_at`, `updated_at` | timestamps | |

`sign_fields`

| Columna | Tipo | Notas |
| --- | --- | --- |
| `id` | bigint PK | interno, solo para claves foráneas |
| `uuid` | uuid, unique | identificador público del campo |
| `document_id` | FK `documents`, cascade | |
| `signer_id` | FK `signers`, cascade | |
| `type` | string, default `firma` | `firma`, `iniciales`, `fecha`, `nombre` |
| `page` | unsigned smallint | base 0 |
| `x`, `y` | decimal(7,4) | % de la página, 0-100 |
| `width`, `height` | decimal(6,2) | tamaño del campo en puntos; por defecto el de `type`, editable por campo |
| `value_path` | string null | PNG de la firma en `signatures/{uuid}.png` |
| `value_text` | string null | valor de texto (fecha, nombre) |
| `created_at`, `updated_at` | timestamps | |

Índices: `documents(user_id, status)`, `signers(document_id)`, `sign_fields(document_id, page)`.

### Modelos y reglas

- `Document`, `Signer` y `SignField` usan el trait `App\Models\Concerns\HasUuid`: rellenan `uuid` con `Str::uuid()` al crearse (evento `creating`) y sobrescriben `getRouteKeyName()` para que las rutas y el enlace por modelo (`route()`, `redirect()->route(...)`) usen `uuid` en vez de `id`. El binding manual de `{document}` en `AppServiceProvider` (limitado a los documentos del usuario autenticado) también resuelve por `uuid`.
- `Document`: `belongsTo` User, `hasMany` Signer y SignField. Cast `status` a `DocumentStatus`, `sent_at` y `completed_at` a fecha. Método `refreshStatus()` que pasa a `completado` cuando todos los firmantes tienen `signed_at`.
- `Signer`: `belongsTo` Document, `hasMany` SignField. Estado de enlace `firmado` si `signed_at` no es null, si no `pendiente`.
- `SignField`: `belongsTo` Document y Signer. Al crearse, si no llegan `width`/`height` toma el tamaño por defecto de su `type`. El valor visible es la imagen (servida por ruta con token, identificada por el `uuid` del campo) o `value_text`.
- `User`: `hasMany` Document.
- Enums `App\Enums\DocumentStatus` y `App\Enums\FieldType`, respaldados por string. `FieldType::size()` sigue dando el tamaño por defecto de cada tipo; el tamaño real de un campo ya creado es el de sus columnas `width`/`height`.
- Factories para los tres modelos, con estados `pendiente()` y `completado()` en la de `Document`.
- Al eliminar un `Document` (evento `deleting`) se borran sus archivos del disco.
- Un documento solo se edita en `borrador`. Enviar exige al menos 1 firmante y 1 campo, y que cada firmante tenga al menos un campo.
- Estado `pendiente` al enviar; `completado` cuando todos los firmantes tienen `signed_at`.
- `App\Http\Resources\DocumentResource` entrega `id` (el `uuid`), `name`, `status`, `date` (formato `29 sep 2026`), `pages`, `signers`, `fields` y `links` con la forma de `DocumentItem`. `SignerResource`, `PublicSignerResource` y `SignFieldResource` entregan `id` y `signerId` como `uuid`, nunca el `id` interno.
- Los formularios que reciben un firmante por id (`POST .../fields`, `PATCH .../fields/{field}`) validan `signer_id` como el `uuid` del firmante (`Rule::exists('signers', 'uuid')->where('document_id', ...)`) y el controlador lo resuelve al `id` interno antes de guardar.

### Rutas

`{document}`, `{signer}` y `{field}` se resuelven por su `uuid` (route model binding con `getRouteKeyName()` sobrescrito), no por el `id` interno; ninguna URL del panel expone un id incremental.

| Ruta | Nombre | Acceso | Descripción |
| --- | --- | --- | --- |
| `GET /documents` | `documents.index` | `auth` | Listado desde la base de datos |
| `POST /documents` | `documents.store` | `auth` | Sube el PDF, crea el borrador y redirige al editor |
| `GET /documents/{document}/editor` | `documents.editor` | `auth`, dueño | Editor |
| `GET /documents/{document}/file` | `documents.file` | `auth`, dueño | Sirve el PDF original |
| `GET /documents/{document}/download` | `documents.download` | `auth`, dueño | Descarga el PDF firmado (404 si no está completado) |
| `POST /documents/{document}/signers` | `signers.store` | `auth`, dueño | Alta de firmante |
| `DELETE /documents/{document}/signers/{signer}` | `signers.destroy` | `auth`, dueño | Baja de firmante y de sus campos |
| `POST /documents/{document}/fields` | `fields.store` | `auth`, dueño | Crea campo |
| `PATCH /documents/{document}/fields/{field}` | `fields.update` | `auth`, dueño | Mueve o reasigna |
| `DELETE /documents/{document}/fields/{field}` | `fields.destroy` | `auth`, dueño | Elimina |
| `POST /documents/{document}/send` | `documents.send` | `auth`, dueño | Valida, genera tokens, pasa a `pendiente` |
| `GET /sign/{token}` | `sign.show` | público | Pantalla de firma |
| `GET /sign/{token}/file` | `sign.file` | público | PDF original para el firmante |
| `GET /sign/{token}/fields/{field}/image` | `sign.image` | público | Imagen de firma de un campo |
| `POST /sign/{token}/fields/{field}` | `sign.field` | público | Guarda la firma (PNG) en un campo propio |
| `POST /sign/{token}/finish` | `sign.finish` | público | Finaliza la firma del firmante |

Reglas de acceso:

- `{document}` se resuelve limitado a los documentos del usuario autenticado; si no existe o no es suyo, responde 404.
- Un `{token}` inexistente responde 404.
- Un firmante solo puede escribir en sus propios campos y solo mientras el documento está `pendiente`; si no, 403. Un `{field}` que existe pero pertenece a otro documento también responde 403 en `sign.field` (comprobación explícita, ya que el binding por `uuid` no está limitado al documento del token) y 404 en `sign.image`.
- Las rutas de firma llevan `throttle:60,1`. Todas las rutas mutantes usan la protección CSRF de Laravel.
- Validación con Form Requests: `StoreDocumentRequest`, `StoreSignerRequest`, `StoreFieldRequest`, `UpdateFieldRequest`, `SignFieldRequest`.

### Almacenamiento local

- Disco `local` (`storage/app/private`), sin URLs públicas. Los archivos se sirven por controlador con `Storage::response()`.
- Rutas de archivo: `documents/{uuid}.pdf`, `signatures/{uuid}.png`, `signed/{uuid}.pdf`.
- La imagen de firma llega como data URL desde el navegador; se decodifica y valida (PNG o JPG, máximo 1 MB) y se guarda como PNG.
- Los PDF subidos se validan por tipo MIME real y se cuentan sus páginas con FPDI.

### Requisitos de entorno (para que funcione sin pasos manuales)

- Extensiones PHP: `gd` (convertir JPG a PNG y validar imágenes), `zlib` (FPDF/FPDI y PNG con transparencia), `fileinfo` (MIME real), `mbstring` y `pdo_mysql` (desarrollo) o `pdo_sqlite` (tests). Todas presentes en el entorno actual; el comando `app:check-environment` no se crea, pero el README lo documenta.
- Base de datos: MySQL en desarrollo (`.env`), SQLite en memoria en tests (`phpunit.xml`, ya configurado). Las migraciones deben ser válidas en ambos motores (sin sintaxis exclusiva de MySQL).
- `upload_max_filesize` y `post_max_size` de PHP deben ser ≥ 12 MB para admitir el límite de 10 MB; si el servidor rechaza el archivo por tamaño, el usuario ve el mismo error de validación y no una página en blanco.
- Disco `local` sin `php artisan storage:link`: no se necesita enlace simbólico porque nada se sirve desde `public/storage`.
- Los directorios `documents/`, `signatures/` y `signed/` se crean al guardar el primer archivo (`Storage::put` los crea); no requieren preparación.
- `DatabaseSeeder` crea el usuario de demostración `test@example.com` (contraseña `password`, ya existente por el factory) y llama a `DocumentSeeder`. Se quita `WithoutModelEvents` del seeder si impide que se dispare algún evento necesario.
- Un usuario recién registrado empieza con el listado vacío ("No hay documentos en esta vista.") y puede subir su primer PDF; no depende de datos sembrados.
- En tests se usa `Storage::fake('local')`, de modo que nunca escriben en `storage/app/private`.

---

## Implementation plan

1. Crear los enums `DocumentStatus` y `FieldType`, las tres migraciones, los modelos con relaciones y casts y sus factories. Añadir `documents()` en `User`. Ejecutar `php artisan migrate` y verificar las tablas y las claves foráneas.
2. Crear `app/Console/Commands/MakeSamplePdf.php` (`app:make-sample-pdf`) que con FPDF genera `public/samples/acuerdo-servicios.pdf` de 2 páginas Letter: título, comparecencia, cláusulas Primera a Cuarta en la página 1, Quinta a Séptima y los bloques "Por el Cliente" y "Por el Prestador" con la línea "Nombre y fecha" en la página 2, en las posiciones de los campos de ejemplo. Ejecutarlo y commitear el PDF. Verificar que abre y tiene 2 páginas.
3. Crear `DocumentSeeder` (invocado desde `DatabaseSeeder`) con los 4 documentos, firmantes y campos del artifact, copiando el PDF de ejemplo a `documents/` en el disco local. Verificar con `php artisan migrate:fresh --seed`.
4. Crear `DocumentResource`, `DocumentController::index()` leyendo de la base de datos (solo los del usuario) y actualizar los tests de SPEC 02 que dependían de `MockData`. Retirar `MockData` si ya no lo usa nadie.
5. Crear `POST /documents` (`StoreDocumentRequest`, guardado en disco local, conteo de páginas, borrador) y `GET /documents/{document}/file`. Conectar el botón "Subir documento" y la zona de arrastre de `documents/Index.vue` con Inertia (`router.post` con `forceFormData`), sustituyendo el aviso. Verificar subiendo un PDF válido y uno inválido.
6. Crear `resources/js/components/PdfPages.vue`: carga el PDF con `pdfjs-dist` (worker importado con `?url`), renderiza cada página en un `canvas` con `data-page` y un contenedor de proporción real escalado por un factor `zoom`, y expone un `slot` por página para superponer campos. Probar con el PDF de ejemplo y comprobar que se ven las 2 páginas nítidas.
7. Crear `resources/js/components/SignFieldBox.vue`: dibuja un campo con borde discontinuo, fondo translúcido del color del firmante, etiqueta y siglas; muestra la imagen o el texto cuando tiene valor; soporta estados seleccionado, propio resaltado y ajeno atenuado.
8. Crear las rutas de firmantes y campos (controladores `SignerController` y `FieldController`, Form Requests y guardas de estado `borrador`) y `GET /documents/{document}/editor` con `documents/Editor.vue`: cabecera, panel de firmantes con alta y baja, y el documento con sus campos. Verificar que el documento 1 sembrado muestra sus dos campos de firma en la página 2.
9. Añadir la paleta y el arrastre: crear campos soltando "Firma" sobre una página, mover campos entre páginas con ajuste a rejilla de 8 puntos, fantasma durante el arrastre y aviso si no hay firmantes. Cada acción llama al servidor y actualiza el estado local con la respuesta.
10. Añadir el panel del campo seleccionado (reasignar, eliminar, Suprimir o Retroceso) y el resumen de campos.
11. Añadir la variante móvil del editor: hoja inferior de firmantes, barra inferior con paleta y selección, y zoom `min(1, (ancho − 24) / 612)`.
12. Crear `documents/LinksModal.vue` y `POST /documents/{document}/send` (validaciones, tokens con `Str::random(64)`, estado `pendiente`, `sent_at`, aviso). Hacer clicables las filas de `documents/Index.vue`. "Copiar" usa el portapapeles y muestra aviso; "Abrir enlace" navega con Inertia a `/sign/{token}` en la misma pestaña.
13. Crear `GET /sign/{token}` con `SignController::show()`, `GET /sign/{token}/file` y `sign/Show.vue`: cabecera, indicaciones, documento con campos propios resaltados y ajenos atenuados, y "Salir" hacia `/documents`.
14. Crear `sign/SignatureModal.vue` con las pestañas Dibujar (`signature_pad`, color `#1a3560`, "Limpiar") y Subir imagen (PNG o JPG, vista previa), recorte del trazo, "Cancelar" y "Adoptar y firmar", y `POST /sign/{token}/fields/{field}` que guarda el PNG en disco. Reutilizar la firma adoptada por firmante y tipo de campo en el cliente, guardando cada campo con su propia petición.
15. Implementar `POST /sign/{token}/finish` con las validaciones y avisos del artifact, `signed_at`, `Document::refreshStatus()` y la transición a `completado`. Volver a `/documents` con el modal de enlaces abierto. Añadir la variante móvil de la pantalla de firma.
16. Crear `App\Services\SignedPdfBuilder` que con FPDI importa cada página del original, estampa las imágenes de firma en `x`, `y` (convertidos de porcentaje a puntos según el tamaño real de la página, con los tamaños de campo del Data model) y guarda en `signed/{uuid}.pdf`; se invoca al pasar a `completado`. Añadir `GET /documents/{document}/download` y el botón "Descargar PDF firmado" en el modal de enlaces de un documento completado.
17. Cambiar el destino de "Probar el editor" en `Welcome.vue` a `/documents`. Escribir los tests de feature (ver criterios), incluido `FullSigningFlowTest`, ejecutar `php artisan test`, `npm run types:check` y `npm run build`, y dejar `public/build` actualizado.
18. Verificación final de punta a punta con el servidor local (`php artisan serve` y `npm run build`, sin `npm run dev`): repetir en el navegador el criterio "Flujo completo", revisar `storage/logs/laravel.log` y la consola, y corregir lo que falle antes de dar la spec por terminada. Documentar en `README.md` los requisitos de entorno y el usuario de demostración.

---

## Acceptance criteria

**Base de datos y almacenamiento**

- [x] `php artisan migrate:fresh --seed` termina sin errores y crea las tablas `documents`, `signers` y `sign_fields` con sus claves foráneas.
- [x] Tras el seeder existen 4 documentos con los estados, firmantes y campos del artifact, y sus PDF están en `storage/app/private/documents`.
- [x] Ningún archivo de documentos, firmas o PDF firmados es accesible por URL directa bajo `public/`; solo por las rutas con control de acceso.
- [x] Eliminar un `Document` borra sus archivos del disco y sus firmantes y campos (cascada).
- [x] `php artisan app:make-sample-pdf` genera `public/samples/acuerdo-servicios.pdf` con exactamente 2 páginas de 612 × 792 puntos.

**Tests y build**

- [x] `php artisan test` pasa completo e incluye tests de feature para: listado solo del dueño, subida válida e inválida (no PDF, mayor de 10 MB), acceso 404 a documentos ajenos, alta y baja de firmantes, crear, mover y eliminar campos, edición bloqueada fuera de `borrador`, envío con y sin campos, firma con token válido e inválido, 403 al firmar campo ajeno, finalizar con campos vacíos, y completado con descarga del PDF firmado.
- [x] `npm run types:check` y `npm run build` terminan sin errores.

**Subida**

- [x] Subir un PDF válido desde `/documents` crea un borrador y abre su editor.
- [x] Subir un archivo que no es PDF, o de más de 10 MB, muestra un error y no crea documento.

**Editor**

- [x] `GET /documents/1/editor` sin sesión redirige a `/login`, y con sesión y siendo dueño responde 200.
- [x] `GET /documents/99/editor` con sesión responde 404, y el editor de un documento de otro usuario también.
- [x] El editor del documento 1 renderiza 2 canvas de PDF y 2 campos de firma en la página 2, uno de Carlos Ruiz (`#1792bb`) y uno de Lucía Fernández (`#7a4fc9`).
- [x] Arrastrar "Firma" de la paleta y soltarla fuera de una página no crea ningún campo.
- [x] Arrastrar "Firma" y soltarla sobre una página crea un campo seleccionado con el color del firmante activo, el resumen aumenta en uno y el campo sigue ahí tras recargar.
- [x] Un campo colocado tiene `x` e `y` múltiplos de 8 puntos antes de convertirse a porcentaje.
- [x] Un campo arrastrado de la página 1 a la página 2 queda con `page` igual a 1 en la base de datos.
- [x] Sin firmantes, arrastrar la paleta muestra "Agrega un firmante primero".
- [x] Agregar un firmante lo guarda en la base de datos y sobrevive a una recarga; eliminarlo borra también sus campos.
- [x] Seleccionar un campo y pulsar Suprimir lo elimina; con el foco en un input, Suprimir no elimina el campo.
- [x] Reasignar un campo a otro firmante cambia su color de inmediato y queda guardado.
- [x] Con un campo seleccionado, cambiar "Ancho (pt)" o "Alto (pt)", o arrastrar el asa de su esquina, cambia el tamaño del campo en pantalla de inmediato y sobrevive a una recarga.
- [x] Ninguna URL de `/documents`, del editor o de los enlaces de firma contiene un id numérico incremental; todas usan `uuid`.

**Envío y enlaces**

- [x] "Enviar para firma" sin campos muestra "Agrega al menos un campo al documento" y no cambia el estado.
- [x] "Enviar para firma" con campos deja el documento en "Pendiente de firma", genera un token único de 64 caracteres por firmante, abre el modal de enlaces y muestra "Enlaces generados para 2 firmantes".
- [x] Un documento en `pendiente` o `completado` rechaza cambios de firmantes y campos con 403.
- [x] Cada fila del modal de enlaces muestra siglas, nombre, correo, una URL `/sign/{token}` y estado, y "Copiar" deja la URL en el portapapeles.
- [x] En `/documents`, hacer clic en un borrador abre el editor y hacer clic en uno pendiente o completado abre el modal de enlaces.

**Firma**

- [x] `GET /sign/{token}` con un token válido responde 200 sin sesión y con un token inexistente responde 404; `GET /sign/{token}/file` devuelve el PDF con el mismo criterio.
- [x] En la pantalla de firma, solo los campos del firmante actual son clicables y el resto tiene opacidad 0.4.
- [x] Dibujar un trazo en el canvas habilita "Adoptar y firmar"; sin trazo el botón no hace nada.
- [x] Adoptar una firma por imagen PNG la guarda en `storage/app/private/signatures` y la muestra dentro del campo, también tras recargar.
- [x] El segundo campo del mismo firmante se rellena al hacer clic sin abrir el modal.
- [x] Intentar firmar el campo de otro firmante por la API responde 403.
- [x] "Finalizar firma" con campos propios vacíos muestra "Completa todos tus campos antes de finalizar" y no marca al firmante.
- [x] Cuando firman todos los firmantes, el documento pasa a "Completado", se guarda `completed_at` y aparece "Documento completado por todos los firmantes".

**PDF final**

- [x] Al completarse, existe `signed/{uuid}.pdf` con el mismo número de páginas que el original y las firmas estampadas en la posición de sus campos.
- [x] El dueño descarga el PDF firmado desde el modal de enlaces; un usuario ajeno o un documento no completado responden 404.

**Flujo completo (criterio de "todo funcional")**

- [x] Con una base recién migrada y sin datos sembrados: registrar un usuario, subir un PDF, agregar 2 firmantes, colocar un campo para cada uno, enviar, abrir cada enlace sin sesión, firmar y finalizar con cada firmante, y descargar el PDF firmado desde el modal, sin errores en el log de Laravel ni en la consola del navegador y sin editar archivos ni ejecutar comandos intermedios.
- [x] Un test de feature `FullSigningFlowTest` recorre ese mismo flujo por HTTP (con `Storage::fake('local')`) y termina con el PDF firmado guardado y el documento en `completado`.
- [x] Un usuario recién registrado ve el listado vacío y puede subir su primer PDF.
- [x] Tras `php artisan migrate:fresh --seed`, iniciar sesión con `test@example.com` / `password` muestra los 4 documentos de ejemplo.
- [x] Subir un JPG como imagen de firma se convierte a PNG y se estampa correctamente en el PDF final.

**Responsive y otros**

- [x] A 375 px de ancho, el editor y la pantalla de firma no tienen scroll horizontal y la página del PDF se escala para caber.
- [x] El botón "Probar el editor" de la landing lleva a `/documents` (y a `/login` si no hay sesión).

---

## Decisions

- **Yes:** incluir modelos, migraciones y backend en esta spec, por decisión del usuario, para que el flujo funcione de extremo a extremo. Sustituye a la versión anterior de esta spec, que dejaba todo en memoria del navegador.
- **Yes:** almacenamiento local con el disco `local` (`storage/app/private`) y servido por controlador. Los documentos son privados y el acceso se controla por sesión o token. Cambiar a S3 será cambiar el disco, sin tocar el código.
- **No:** guardar archivos en `storage/app/public`. Cualquiera con la URL podría leer documentos.
- **Yes:** tres tablas (`documents`, `signers`, `sign_fields`) con `SignField` separado del firmante. Un campo tiene posición y valor propios, y es lo que se estampa.
- **Yes:** guardar la firma como archivo PNG en disco y no como data URL en la base de datos. Mantiene las filas pequeñas y facilita estampar con FPDI.
- **Yes:** token de firma de 64 caracteres con `Str::random(64)`, único en base de datos. Sustituye al token derivado de SHA-1 de la versión anterior. No caduca ni se revoca en esta spec.
- **Yes:** renderizar un PDF real con `pdfjs-dist`. Valida desde ya el posicionamiento de campos sobre páginas reales, y la dependencia ya está instalada desde SPEC 01.
- **Yes:** posiciones de campo en porcentaje de la página, `page` base 0. No depende de la resolución de pantalla ni del zoom, y se traduce directo a puntos de PDF al estampar.
- **Yes:** generar el PDF de ejemplo con FPDF mediante un comando Artisan y commitearlo. Es reproducible y usa una librería ya instalada.
- **Yes:** estampar la firma con FPDI en esta spec. Sin PDF final el producto no cumple su función; FPDF y FPDI ya están instalados.
- **Yes:** `signature_pad` para dibujar la firma, con recorte del trazo. Ya está instalado desde SPEC 01.
- **Yes:** el servidor es la fuente de verdad. El cliente mantiene un estado reactivo local que se actualiza con la respuesta de cada petición, sin `localStorage`.
- **Yes:** "Abrir enlace" navega en la misma pestaña, para poder volver a `/documents` con el modal abierto.
- **Yes:** la paleta solo tiene el campo "Firma", como el artifact. Los otros tipos existen en el modelo y se renderizan y estampan si vienen en los datos.
- **Yes:** los documentos se editan solo en `borrador`. Evita que cambien campos ya enviados a los firmantes.
- **Yes:** el botón "Probar el editor" apunta a `/documents`, porque ya no hay un id de ejemplo fijo garantizado. Sin sesión, `auth` lo manda a `/login`.
- **Yes:** exponer `uuid` en vez del `id` incremental en toda URL, respuesta JSON y prop de Inertia de `Document`, `Signer` y `SignField` (decisión del usuario durante la implementación). Evita enumerar recursos ajenos por id secuencial; el `id` interno solo sirve de clave foránea. Se implementa con un trait `HasUuid` compartido en vez de repetir el evento `creating` y `getRouteKeyName()` en cada modelo.
- **Yes:** el token del enlace de firma pasa de `Str::random(64)` a `Str::uuid()` (36 caracteres), por pedido explícito del usuario de un identificador "no tan largo". Sigue siendo distinto del `uuid` del firmante: el `uuid` identifica el recurso en el panel, el `token` es la credencial pública del enlace y se puede regenerar o revocar sin cambiar el primero.
- **Yes:** ancho y alto de cada campo de firma son editables por campo, no fijos por `type` (decisión del usuario: "no siempre debe ser lo mismo"). El tamaño por defecto al crear un campo sigue viniendo de `FieldType::size()`. Al estampar, la imagen se ajusta ("contain") al tamaño guardado del campo, igual que ya se mostraba en pantalla.
- **No:** dejar el ancho y el alto fijos por `type` como antes. No cubre firmas con proporciones distintas entre firmantes o documentos.

---

## Risks

| Risk | Mitigation |
| --- | --- |
| El worker de pdf.js no carga desde `public/build` en el hosting o falla al minificarse. | Importar el worker con `?url` para que Vite lo emita como asset y comprobar el criterio del canvas con `npm run build` y sin `npm run dev`. |
| La versión 6 de `pdfjs-dist` cambió la API respecto a lo habitual en ejemplos. | Consultar la documentación de la versión instalada (6.3.289) antes de usar `getDocument` y `render`. |
| Los campos se desalinean del PDF en pantallas con `devicePixelRatio` alto. | El canvas se escala por `devicePixelRatio` pero el contenedor mide siempre el tamaño de página × `zoom` en puntos CSS, y los campos usan porcentajes del contenedor. |
| Eventos de puntero durante el arrastre se pierden en táctil. | Usar Pointer Events con captura del puntero y `touch-action: none` en los campos y en la paleta. |
| FPDI no puede importar PDF con compresión de referencias cruzadas 1.5+ (la versión gratuita solo lee hasta PDF 1.4). | Detectarlo al subir: si FPDI falla al contar páginas, rechazar el archivo con un mensaje claro. Dejar el soporte de PDF modernos (FPDI PDF-Parser o Ghostscript) para una spec futura y documentarlo en los tests. |
| Páginas de PDF subidos con tamaño distinto de Letter o con rotación desalinean los campos al estampar. | Calcular puntos a partir del tamaño real de cada página importada con FPDI y no del 612 × 792 fijo. Cubrirlo con un test con una página A4. |
| Subidas grandes o maliciosas. | Límite de 10 MB y 50 páginas, validación por MIME real, nombre de archivo generado con UUID, nunca el original. |
| Un firmante manipula la petición para firmar campos de otros o de otro documento. | Resolver siempre el campo a través del firmante del token (`$signer->fields()->findOrFail`) y bloquear si el documento no está `pendiente`. |
| Imágenes de firma enormes o con contenido no PNG/JPG. | Validar prefijo del data URL, decodificar y comprobar con `getimagesizefromstring`, máximo 1 MB. |
| Tildes y eñes del texto de ejemplo se rompen en FPDF (usa ISO-8859-1). | Convertir el texto con `iconv('UTF-8', 'windows-1252//TRANSLIT', ...)` al generar el PDF y revisar el resultado. |
| Los tests de SPEC 02 dependían de `MockData`. | El paso 4 los actualiza para crear documentos con factories. |

---

## What is **not** in this spec

- Almacenamiento en S3 u otra nube.
- Envío de enlaces por correo y verificación de email.
- Caducidad, revocación de enlaces, auditoría, sello de tiempo y certificado de firma.
- Campos de iniciales, fecha y nombre en la paleta.
- Eliminar, renombrar o reabrir documentos.
- Tests con navegador.

Cada uno de estos puntos, si se hace, va en su propia spec.
