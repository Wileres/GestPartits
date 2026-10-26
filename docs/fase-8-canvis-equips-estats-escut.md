# GestPartits — Cambios del 18/10/2026: horario preferente, estados y escudo único

> Estado: terminado.

## Qué cambia

| Petición | Implementación |
|---|---|
| Editar el horario preferente de cada equipo desde su ficha | **Equips → Edita** tiene la sección «Horari preferent a casa» (día, hora en cuartos de hora y campo opcional). Se guarda en `PUT /api/teams/{id}` (`preferred_schedule`; `null` lo borra; sin la clave no se toca) como la regla de preferencia del propio equipo (se reutiliza la que ya existía, conservando su prioridad y tipo). La pueden editar los gestores y los administradores. Si el equipo no tiene regla propia, la ficha muestra la heredada de la categoría o la modalidad |
| Un partido nuevo en casa carga el horario preferente | `MatchService` (alta individual y hoja rápida) usa `PreferredScheduleService`: el día de la jornada que coincide con el día de la regla, la hora preferente y el campo preferente (Camp complet si es F11; si no hay campo preferente, el primer campo libre). Si el equipo no tiene preferencia, la jornada no tiene ese día o se da una hora, no se hace nada. No se bloquea: la propuesta puede recolocarlo si hay un conflicto |
| Convocatoria por defecto: una hora antes | `CallTime`: al crear un partido en casa con hora, o al darle hora por primera vez, la convocatoria es la hora del partido menos 60 min. Al mover el partido (calendario o propuesta), una convocatoria por defecto lo sigue; una propia no se toca. En el panel del partido, al elegir la hora se propone la convocatoria (se puede cambiar) |
| Estados: solo Proposat, Confirmat y Cancel·lat | El enum, la restricción `CHECK` de la base de datos y el frontend. Un partido nuevo se guarda como **Confirmat**. La migración convierte los datos: pendiente → confirmado, aplazado → cancelado y los «descansa» se borran lógicamente |
| Un único escudo para todos los equipos | Se elimina `teams.logo_path` y los endpoints por equipo. El escudo se sube en Configuració → Aparença (una sola vez), se recodifica a PNG y se sirve por `GET /api/public/logo` (público, para el login) junto a `GET /api/public/branding`. Sale en el login, el menú, la lista de equipos y las imágenes de Instagram |
| Partidos en casa resaltados en azul suave | En «Partits», las filas (y las tarjetas del móvil) de los partidos a casa, incluidos los equipos con «Casa» sugerido todavía sin crear, llevan un fondo `primary/6 %` |

## Consecuencias de quitar «Confirmat = fijo»

Antes, un partido confirmado no lo movía la propuesta automática. Como ahora **todos** los partidos nacen confirmados, esa regla dejaría la propuesta sin efecto. Por eso **solo el bloqueo** (candado) protege un partido. Una colocación manual (arrastrar o editar la hora) sigue bloqueándolo automáticamente.

«Confirma la programació» sigue siendo la confirmación de toda la jornada (es otra cosa que el estado del partido): pasa a «Confirmat» los partidos que aún eran «Proposat».

## Notas

- **Descansa:** ya no existe. Un equipo que no juega simplemente no tiene fila de partido (en la hoja rápida se deja en «Sense partit»).
- **Sin escudo todavía:** hasta que alguien suba el escudo oficial en Aparença, se ve un escudo genérico azul y blanco «SPM». El proyecto no incluye el escudo real del club.
- **Datos existentes:** las migraciones `2026_10_18_000100` y `2026_10_18_000200` se aplican con `php artisan migrate` y no pierden partidos.

## Pruebas

Backend: **268** (11 nuevas para el horario preferente y la convocatoria, 4 para la edición desde la ficha, 8 del escudo único, más las adaptadas a los nuevos estados). Frontend: **76** (edición del horario desde la ficha, validación, herencia de la categoría, convocatoria por defecto, estados y escudo en el login).

## Addenda (2026-10-25)

- **Jornada per defecte:** `useMatchdaySelection` tria l'última jornada creada (D-068). Prova: `help.test.tsx`.
- **Ajuda:** rutes `/ajuda/guia-rapida` i `/ajuda/guia-completa`, components a `features/help/HelpGuides.tsx` i textos a `ca.help` (D-069).
- **Convocatòria, dia i hora a fora, peu de les imatges, avisos i enllaç de WhatsApp:** D-062 a D-067.

## Addenda (2026-10-26): imatges, resultats i xarxes socials

- **Resultats** a cada partit (D-070) i correcció del bloqueig espuri en editar un partit a casa (D-071).
- **Històric d'imatges** amb esborrat manual (D-072), opció d'agrupar a casa i a fora (D-073) i **imatge de resultats** de la jornada (D-074).
- **Editor per a xarxes socials** al navegador (D-075).
- Migracions: `2026_10_26_000100_add_match_results` i `2026_10_26_000200_generated_images_history`.

## Addenda (2026-10-27): format de publicació i mode comprimit

- Format d'imatge: història o publicació d'Instagram (D-076); imatge de resultats sempre com a publicació i amb les files estirades.
- Mode comprimit amb caixes proporcionals al nombre de partits (D-077) i mides del text per fila (D-078).
- Les generacions antigues, sense format guardat, es mostren com a històries.

