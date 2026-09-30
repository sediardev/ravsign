# SPEC 03 — Editor de campos y pantalla de firma sobre PDF

> **Status:** Aprobado
> **Depends on:** SPEC 02
> **Date:** 2026-09-29
> **Objective:** Construir en Vue el editor de campos de firma, la pantalla de firma con dibujo o imagen y el modal de enlaces del artifact de Ravsign, renderizando un PDF de ejemplo con pdf.js y con estado local y datos de ejemplo.

---

## Por qué existe esta spec

Es la segunda mitad del diseño del artifact (`https://claude.ai/artifact/3uiQ7uzUKAkkUA1BYz4165`) y contiene toda la interacción: arrastrar campos sobre el documento, asignarlos a firmantes y firmar. El prototipo simulaba el documento con HTML. Aquí se usa un PDF real renderizado con `pdfjs-dist`, para validar desde ya el posicionamiento de campos sobre páginas reales, que es la parte técnica más delicada del producto.

Sigue sin haber backend de documentos: todo se guarda en un estado del navegador y se pierde al recargar la página.

Convenciones de coordenadas (iguales a las del artifact):

- Tamaño base de página: 612 × 792 puntos (Letter).
- Posición de un campo: `x` e `y` en porcentaje de la página, origen arriba a la izquierda, `page` base 0.
- Tamaños de campo en puntos: firma 176 × 58, iniciales 92 × 58, fecha 132 × 36, nombre 176 × 36.
- Ajuste a rejilla de 8 puntos al soltar un campo.
- En móvil la página se escala a `min(1, (ancho de ventana − 24) / 612)`.
- Colores de firmantes en orden: `#1792bb`, `#7a4fc9`, `#d27a1f`, `#2f9e6b`.

---

## Scope

**In:**

- PDF de ejemplo de 2 páginas (Letter) con el "Acuerdo de prestación de servicios" del artifact, generado con FPDF y guardado en `public/samples/acuerdo-servicios.pdf`.
- Comando Artisan `app:make-sample-pdf` que lo genera de forma reproducible.
- Componente Vue que renderiza cada página del PDF con `pdfjs-dist` en un `canvas` y superpone los campos en porcentajes.
- Editor en `/documents/{id}/editor` (requiere sesión):
  - Cabecera con "← Documentos", nombre del documento, subtítulo con estado y número de firmantes, pasos "1 Preparar / 2 Firmar" y botón "Enviar para firma".
  - Panel de firmantes: lista, alta con nombre, correo y siglas (máximo 4, por defecto las iniciales del nombre), baja con "×" y selección del firmante activo.
  - Paleta con el campo "Firma" que se arrastra al documento, con fantasma mientras se arrastra.
  - Mover campos ya colocados entre páginas, seleccionar, reasignar a otro firmante con un selector, eliminar con botón y con las teclas Suprimir o Retroceso.
  - Resumen "N campos en el documento".
  - Variante móvil: firmantes en hoja inferior, paleta y selección en barra inferior.
  - Aviso "Agrega un firmante primero" y "Agrega al menos un campo al documento" cuando corresponda.
- Al enviar para firma: el documento pasa a `pendiente`, se muestra el listado y se abre el modal de enlaces con el aviso "Enlaces generados para N firmantes".
- Modal "Enlaces de firma" en `/documents`: una fila por firmante con siglas, nombre, correo, URL, estado, "Copiar" (portapapeles y aviso) y "Abrir enlace".
- Filas del listado clicables: un borrador abre el editor; un documento pendiente o completado abre el modal de enlaces.
- Pantalla de firma en `/sign/{token}` (pública, sin sesión), con "Firmando como {nombre}", "Finalizar firma", "Salir", "Tu enlace de firma", campos propios resaltados y campos ajenos atenuados.
- Modal de firma con pestañas "Dibujar" (canvas con `signature_pad`, botón "Limpiar") y "Subir imagen" (PNG o JPG, vista previa, "Haz clic para cambiar la imagen"), texto de consentimiento y botones "Cancelar" y "Adoptar y firmar".
- Recorte automático del trazo dibujado y reutilización de la firma adoptada por el mismo firmante en sus otros campos.
- "Finalizar firma": valida que todos los campos propios estén firmados, marca al firmante como firmado y pasa el documento a `completado` cuando firmaron todos, con los avisos del artifact.
- Estado compartido entre pantallas en un store reactivo del navegador (`resources/js/stores/documents.ts`), inicializado con los datos del servidor y en memoria.
- Variante móvil de la pantalla de firma con barra inferior y sugerencia "Toca cada campo resaltado para firmar."
- Cambiar el botón "Probar el editor" de la landing para que apunte a `/documents/1/editor`.
- Build recompilado.

**Out of scope (para specs futuras):**

- Subir PDF reales del usuario y guardar documentos, firmantes o campos en la base de datos.
- Estampar las firmas en el PDF final con FPDI y descargar el resultado.
- Tokens de firma reales, expiración de enlaces, auditoría y sello de tiempo.
- Envío de enlaces por correo.
- Campos de iniciales, fecha y nombre en la paleta (el artifact solo expone "Firma"). Sí se renderizan si vienen en los datos.
- Persistencia entre recargas (el estado vive en memoria).
- Zoom, miniaturas y navegación entre páginas del PDF más allá del scroll.
- Gestos táctiles avanzados (pellizcar para hacer zoom).
- Tests end-to-end con navegador.

---

## Data model

Se reutilizan los tipos `Signer`, `SignField`, `DocumentItem`, `DocumentStatus` y `FieldType` definidos en SPEC 02 (`resources/js/types/documents.ts`), y `App\Support\MockData` como origen de los datos. Se añaden:

```ts
// resources/js/types/documents.ts
export interface SignLink {
    signerId: string;
    url: string; // absoluta, '/sign/{token}'
    token: string; // 16 caracteres hexadecimales
    status: 'pendiente' | 'firmado';
}

export interface AdoptedSignatures {
    // clave: `${signerId}:${fieldType}`, valor: data URL PNG
    [key: string]: string;
}
```

Store del navegador (`resources/js/stores/documents.ts`), en memoria:

```ts
interface DocumentsStore {
    documents: DocumentItem[]; // se siembra desde props de Inertia solo si está vacío
    adopted: AdoptedSignatures;
    selectedFieldId: string | null;
    activeSignerId: string | null;
}
```

Reglas:

- El token de un enlace es `substr(sha1('ravsign-mock-' . $documentId . '-' . $signerId), 0, 16)`, calculado en `MockData::linkFor()`. No es seguro y solo sirve para el ejemplo.
- `status` de un enlace es `firmado` cuando todos los campos de ese firmante tienen `value`.
- El estado de un documento pasa de `borrador` a `pendiente` al enviar, y de `pendiente` a `completado` cuando todos los campos tienen `value`.

Rutas nuevas:

| Ruta | Nombre | Middleware | Página |
| --- | --- | --- | --- |
| `GET /documents/{id}/editor` | `documents.editor` | `auth` | `documents/Editor.vue` |
| `GET /sign/{token}` | `sign.show` | ninguno | `sign/Show.vue` |

`{id}` es el `id` numérico del ejemplo (1 a 4); un id inexistente responde 404. Un `{token}` inexistente responde 404.

---

## Implementation plan

1. Crear `app/Console/Commands/MakeSamplePdf.php` (`app:make-sample-pdf`) que con FPDF genera `public/samples/acuerdo-servicios.pdf` de 2 páginas Letter: título, comparecencia, cláusulas Primera a Cuarta en la página 1, Quinta a Séptima y los bloques "Por el Cliente" y "Por el Prestador" con la línea "Nombre y fecha" en la página 2, en las posiciones de los campos de ejemplo. Ejecutarlo y commitear el PDF. Verificar que el archivo abre y tiene 2 páginas.
2. Crear `resources/js/components/PdfPages.vue`: carga el PDF con `pdfjs-dist` (worker importado con `?url`), renderiza cada página en un `canvas` con `data-page` y un contenedor de 612 × 792 puntos escalado por un factor `zoom`, y expone un `slot` por página para superponer campos. Probarlo en una página temporal con el PDF de ejemplo y comprobar que se ven las 2 páginas nitidas.
3. Crear `resources/js/stores/documents.ts` con las acciones `seed`, `addSigner`, `removeSigner`, `addField`, `updateField`, `removeField`, `setStatus`, `adopt`, `finishSigning` y las reglas de estado del Data model. Añadir `MockData::linkFor()` y `MockData::documentByToken()`.
4. Crear `resources/js/components/SignFieldBox.vue`: dibuja un campo con borde discontinuo, fondo translúcido del color del firmante, etiqueta y siglas; muestra la imagen o el texto cuando tiene `value`; soporta estados seleccionado, propio resaltado y ajeno atenuado.
5. Añadir `GET /documents/{id}/editor` (`documents.editor`) con `DocumentController::editor()` y crear `documents/Editor.vue` con la cabecera, el panel de firmantes (alta y baja) y el documento con los campos existentes en su posición. Verificar que el documento 1 muestra sus dos campos de firma en la página 2.
6. Añadir la paleta y el arrastre: crear campos soltando "Firma" sobre una página, mover campos entre páginas con ajuste a rejilla de 8 puntos, fantasma durante el arrastre y aviso si no hay firmantes.
7. Añadir el panel del campo seleccionado (reasignar, eliminar, tecla Suprimir o Retroceso) y el resumen de campos.
8. Añadir la variante móvil del editor: hoja inferior de firmantes, barra inferior con paleta y selección, y zoom `min(1, (ancho − 24) / 612)`.
9. Crear `documents/LinksModal.vue`, "Enviar para firma" (validaciones, estado `pendiente`, aviso) y hacer clicables las filas de `documents/Index.vue` (borrador al editor; pendiente o completado al modal). "Copiar" usa el portapapeles y muestra aviso; "Abrir enlace" navega con Inertia a `/sign/{token}` en la misma pestaña para conservar el estado.
10. Añadir `GET /sign/{token}` (`sign.show`, pública) con `SignController::show()` y crear `sign/Show.vue`: cabecera, indicaciones, documento con campos propios resaltados y ajenos atenuados, y "Salir" hacia `/documents`.
11. Crear `sign/SignatureModal.vue` con las pestañas Dibujar (`signature_pad`, color `#1a3560`, "Limpiar") y Subir imagen (PNG o JPG, vista previa), recorte del trazo, "Cancelar" y "Adoptar y firmar", y la reutilización de la firma adoptada por firmante y tipo de campo.
12. Implementar "Finalizar firma" con las validaciones y avisos del artifact, la transición a `completado` y el regreso a `/documents` con el modal de enlaces abierto. Añadir la variante móvil de la pantalla de firma.
13. Cambiar el destino de "Probar el editor" en `Welcome.vue` a `/documents/1/editor`. Ejecutar `npm run build` y dejar `public/build` actualizado.

---

## Acceptance criteria

- [ ] `php artisan app:make-sample-pdf` genera `public/samples/acuerdo-servicios.pdf` con exactamente 2 páginas de 612 × 792 puntos.
- [ ] `php artisan test` pasa completo.
- [ ] `npm run types:check` y `npm run build` terminan sin errores.
- [ ] `GET /documents/1/editor` sin sesión redirige a `/login`, y con sesión responde 200.
- [ ] `GET /documents/99/editor` con sesión responde 404.
- [ ] El editor del documento 1 renderiza 2 canvas de PDF y 2 campos de firma en la página 2, uno de Carlos Ruiz (`#1792bb`) y uno de Lucía Fernández (`#7a4fc9`).
- [ ] Arrastrar "Firma" de la paleta y soltarla fuera de una página no crea ningún campo.
- [ ] Arrastrar "Firma" y soltarla sobre una página crea un campo seleccionado con el color del firmante activo, y el resumen aumenta en uno.
- [ ] Un campo colocado tiene `x` e `y` múltiplos de 8 puntos antes de convertirse a porcentaje.
- [ ] Un campo arrastrado de la página 1 a la página 2 queda con `page` igual a 1.
- [ ] Sin firmantes, arrastrar la paleta muestra "Agrega un firmante primero".
- [ ] Seleccionar un campo y pulsar Suprimir lo elimina; con el foco en un input, Suprimir no elimina el campo.
- [ ] Reasignar un campo a otro firmante cambia su color de inmediato.
- [ ] "Enviar para firma" sin campos muestra "Agrega al menos un campo al documento" y no cambia el estado.
- [ ] "Enviar para firma" con campos deja el documento en "Pendiente de firma", abre el modal de enlaces y muestra "Enlaces generados para 2 firmantes".
- [ ] Cada fila del modal de enlaces muestra siglas, nombre, correo, una URL `/sign/{16 hex}` y estado, y "Copiar" deja la URL en el portapapeles.
- [ ] En `/documents`, hacer clic en un borrador abre el editor y hacer clic en uno pendiente o completado abre el modal de enlaces.
- [ ] `GET /sign/{token}` con un token válido responde 200 sin sesión y con un token inexistente responde 404.
- [ ] En la pantalla de firma, solo los campos del firmante actual son clicables y el resto tiene opacidad 0.4.
- [ ] Dibujar un trazo en el canvas habilita "Adoptar y firmar"; sin trazo el botón no hace nada.
- [ ] Adoptar una firma por imagen PNG muestra esa imagen dentro del campo.
- [ ] El segundo campo del mismo firmante se rellena al hacer clic sin abrir el modal.
- [ ] "Finalizar firma" con campos propios vacíos muestra "Completa todos tus campos antes de finalizar".
- [ ] Cuando firman todos los firmantes, el documento pasa a "Completado" y aparece "Documento completado por todos los firmantes".
- [ ] A 375 px de ancho, el editor y la pantalla de firma no tienen scroll horizontal y la página del PDF se escala para caber.
- [ ] Recargar la página restablece los datos de ejemplo originales.
- [ ] El botón "Probar el editor" de la landing lleva a `/documents/1/editor` (y a `/login` si no hay sesión).

---

## Decisions

- **Yes:** renderizar un PDF real con `pdfjs-dist`. Valida desde ya el posicionamiento de campos sobre páginas reales, y la dependencia ya está instalada desde SPEC 01.
- **No:** simular el documento con HTML como el prototipo. Habría que rehacer el editor al pasar a PDF.
- **Yes:** generar el PDF de ejemplo con FPDF mediante un comando Artisan y commitearlo. Es reproducible y usa una librería ya instalada.
- **Yes:** posiciones de campo en porcentaje de la página, con base 612 × 792 y `page` base 0. No depende de la resolución de pantalla ni del zoom, y se traduce directo a puntos de PDF al estampar.
- **Yes:** `signature_pad` para dibujar la firma. Ya está instalado desde SPEC 01; sustituye al dibujado manual del prototipo. Se conserva el recorte del trazo.
- **Yes:** estado compartido en un store en memoria del navegador, sembrado desde el servidor. Editor, listado y firma comparten datos aunque sean rutas distintas. No usa `localStorage`, así que no hay que versionar ni migrar formato.
- **Yes:** "Abrir enlace" navega en la misma pestaña. Una pestaña nueva reiniciaría el estado en memoria y las firmas hechas no se verían.
- **Yes:** la ruta de firma es pública y usa un token de 16 hexadecimales derivado de SHA-1. Es un token de ejemplo, no de seguridad; los tokens reales se aleatorizan y se guardan en la spec de backend.
- **Yes:** la paleta solo tiene el campo "Firma", como el artifact. Los otros tipos existen en el modelo y se renderizan si vienen en los datos.
- **No:** estampar la firma en el PDF, descargar el resultado o guardar nada. Es el trabajo del backend con FPDI.
- **Yes:** el botón "Probar el editor" de la landing apunta al editor del documento 1, como en el artifact. Sin sesión, `auth` lo manda a `/login`.

---

## Risks

| Risk | Mitigation |
| --- | --- |
| El worker de pdf.js no carga desde `public/build` en el hosting o falla al minificarse. | Importar el worker con `?url` para que Vite lo emita como asset y comprobar el criterio del canvas con `npm run build` y sin `npm run dev`. |
| La versión 6 de `pdfjs-dist` cambió la API respecto a lo habitual en ejemplos. | Consultar la documentación de la versión instalada (6.3.289) antes de usar `getDocument` y `render`. |
| Los campos se desalinean del PDF en pantallas con `devicePixelRatio` alto. | El canvas se escala por `devicePixelRatio` pero el contenedor mide siempre 612 × 792 × `zoom` puntos CSS, y los campos usan porcentajes del contenedor. |
| Eventos de puntero durante el arrastre se pierden en táctil. | Usar Pointer Events con captura del puntero y `touch-action: none` en los campos y en la paleta. |
| El estado en memoria se pierde si el usuario recarga en mitad de una firma. | Es intencional en esta spec y está en los criterios de aceptación. La persistencia llega con el backend. |
| Tildes y eñes del texto de ejemplo se rompen en FPDF (usa ISO-8859-1). | Convertir el texto con `iconv('UTF-8', 'windows-1252//TRANSLIT', ...)` al generar el PDF y revisar el resultado. |

---

## What is **not** in this spec

- Subir PDF propios ni guardar nada en base de datos.
- Estampar firmas en el PDF final o descargarlo.
- Tokens seguros, caducidad de enlaces, correos y auditoría.
- Campos de iniciales, fecha y nombre en la paleta.
- Persistencia entre recargas y tests con navegador.

Cada uno de estos puntos, si se hace, va en su propia spec.
