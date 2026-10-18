# GestPartits — Fase 6: comunicaciones e imágenes

> Estado: terminada. Fecha: 2026-10-16.

> **Nota (2026-10-18, D-056 a D-061):** els partits només tenen els estats **Proposat, Confirmat i Cancel·lat** (un partit nou és *Confirmat*; «Pendent», «Ajornat» i «Descansa» ja no existeixen: un equip sense partit simplement no en té). L'**escut** és únic per a tot el club (Configuració → Aparença) i ja no es puja per equip. L'**horari preferent** de cada equip s'edita des de la seva fitxa i es carrega en crear un partit a casa, amb la convocatòria una hora abans. Els partits **ja no es protegeixen pel seu estat**: només el bloqueig impedeix que la proposta automàtica els mogui.

## Imágenes para Instagram

- **Dominio** (`app/Domain/Imaging`): `DayPoster`, `PosterEntry` y `LayoutPlanner`, que decide la paginación con alturas fijas:
  - hasta **8** partidos: diseño normal;
  - hasta **10**: diseño compacto, con aviso;
  - más de 10: varias páginas del mismo día (10 por página), con aviso.

  El texto nunca baja de 30 px. Los nombres de más de 26 caracteres se recortan con «…» y también generan un aviso.
- **Renderizadores** (`ScheduleImageRenderer`, se eligen con `IMAGE_RENDERER`):
  - `ChromiumRenderer` (por defecto): vista Blade `images/day-poster` con CSS propio, la fuente Inter (`resources/fonts`, licencia OFL) y el escudo incrustados, iconos SVG (sin emojis, para no depender de una fuente de emojis) y sin ninguna petición de red. Genera un PNG de **1080 × 1920**.
  - `GdRenderer`: alternativa sin Chromium (fuentes DejaVu).
  - `FakeRenderer`: para las pruebas.
- **Servicio** (`ImageService`): solo con la programación **confirmada**. Incluye los partidos en casa y fuera con hora y «Apareix a la imatge», en orden cronológico. Los partidos fuera muestran la instalación y el municipio.
  - Las imágenes se guardan en el disco privado (`images/{programació}/{versió}/…`) y se sustituyen las de la misma versión.
  - Se generan con el trabajo `GenerateScheduleImages` (cola). La pantalla consulta el estado cada 2 s mientras dura la generación.
- **Descarga:** `GET /api/generated-images/{id}`, solo para usuarios autenticados.
- **Aparença** (`/configuracio/aparenca`): nombre del club, colores y escudo (recodificado a PNG).

## WhatsApp

- **Dominio** (`app/Domain/Messaging`):
  - `TemplateRenderer`: lista cerrada de variables, sin evaluar código. Una línea con una variable vacía se elimina.
  - `WhatsAppProviderInterface` y `ProviderCapabilities`.
- **Proveedores** (`app/Infrastructure/WhatsApp`, se activan desde el `.env`):

  | Proveedor | Destinos | Notas |
  |---|---|---|
  | `manual` (siempre activo, vía A) | grupos, canal, listas, teléfonos | Copiar, abrir WhatsApp (`wa.me/?text=`; los canales se abren con el enlace de invitación) y «Marca com a enviat» |
  | `gateway` (vía B, `WHATSAPP_GATEWAY_ENABLED`) | grupos `…@g.us`, canales `…@newsletter`, teléfonos | Pasarela HTTP genérica con un número dedicado. **Incumple los términos de WhatsApp**: hay riesgo de bloqueo del número |
  | `meta` (`WHATSAPP_META_ENABLED`) | teléfonos | Cloud API con una plantilla aprobada (`provider_template_name`) |
  | `twilio` (`WHATSAPP_TWILIO_ENABLED`) | teléfonos | |
  | `log` (desarrollo) | todos | Simulación: escribe en el log |
- **Flujo** (`NotificationService`):
  1. `preview` crea los borradores: uno por partido y grupo del equipo, y un resumen de la jornada por canal del club, con las imágenes adjuntas si está marcado.
  2. Editar es opcional: el texto editado se mantiene mientras no cambie la versión confirmada.
  3. `send` exige `confirm: true` y una transición atómica `draft → queued`.
  4. El trabajo `SendWhatsAppMessage` hace un solo intento: `queued → sending → sent | failed | unknown`.
  5. «Reintenta els fallits» solo actúa sobre los `failed`. Los `unknown` no se reintentan: el gestor lo comprueba y los marca como enviados si han llegado.
- **Sin duplicados:** la clave de idempotencia es única y se calcula con la programación, el partido o el resumen, el destino y el hash del texto. Una nueva previsualización no vuelve a preparar un texto ya enviado. Si el horario cambia y se vuelve a confirmar, el mensaje anterior pasa a `outdated` y se prepara un borrador nuevo.
- **Privacidad:** el identificador del destino (teléfono o id del grupo) se guarda cifrado y la API solo devuelve los últimos 4 caracteres. La respuesta del proveedor se guarda saneada.
- **Pantallas:**
  - **Avisos** (`/avisos`): canal del club y grupos de los equipos, texto editable, copiar o abrir WhatsApp, marcar como enviado, enviar los seleccionados (con un diálogo de confirmación que lista los destinos), reintentar los fallidos e historial.
  - **Configuració → WhatsApp:** plantillas (con ayuda de variables) y destinos.

## Pruebas

- **Backend: 240.** Imágenes de sábado y domingo a 1080 × 1920 (caso 21), paginación, regeneración, imágenes no públicas y **una prueba que genera una imagen real con Chromium** (se omite si no está instalado). Avisos: catalán, canal con imágenes, sin duplicados (caso 16), confirmación obligatoria, manual, desactualizados, reintento solo de los fallidos, estado desconocido sin reintento, datos que no se filtran, permisos (caso 17) y validación de plantillas y destinos.
- **Frontend: 63.**

## Revisión en navegador

Recorrido completo en Chromium headless: confirmar, «Genera les imatges» (la cola procesada con `queue:work`), previsualización de las dos imágenes, «Prepara els avisos» y resumen del canal con las imágenes adjuntas.

Incidencias encontradas y corregidas:
- `preg_split('/\R/')` sin el modificador `u` partía emojis que contienen el byte `0x85`.
- La relación de los adjuntos usaba una clave foránea incorrecta.
- Se sobrescribían los textos editados al volver a previsualizar.
- En la versión GD, el texto largo se solapaba con «A CASA».
