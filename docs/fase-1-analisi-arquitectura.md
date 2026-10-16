# GestPartits — Fase 1: análisis y arquitectura

> Estado: **pendiente de revisión**. No se ha escrito código de aplicación.
> Fecha: 2026-10-15. Registro de decisiones: [DECISIONS.md](DECISIONS.md).

> **Nota (2026-10-16, D-040 y D-041):** en la interfaz, la unidad de planificación que este documento llama «fin de semana» (`matchdays`) se llama **«Jornada»**, y todas las fechas se muestran en formato **DD/MM/AAAA**.

---

## 0. Respuestas del club (2026-10-16)

| # | Pregunta | Respuesta | Consecuencia en el diseño |
|---|----------|-----------|---------------------------|
| P1 | ¿Las 13:00 son la hora máxima de inicio o la hora de cierre? | **Son la última hora de inicio.** El gestor puede colocar un partido manualmente a cualquier hora entre las 09:00 y las 21:00. Los inicios van **siempre en bloques de 15 minutos** (10:00, 10:15…, nunca 10:10). | Cada franja tiene `opens_at` y `last_kickoff_at` y solo limita **el inicio**: el partido puede terminar después. La propuesta automática solo usa las franjas. Un horario manual fuera de las franjas pero dentro del **horario operativo** (09:00–21:00, configurable) da un **aviso ámbar**. Fuera del horario operativo o fuera de la cuadrícula de 15 minutos es un **error rojo**. Se eliminan `closes_at` y el conflicto «Acaba després del tancament» (D-006, D-021). |
| P2 | ¿Hay preparación antes del partido? | **No.** | Se elimina el concepto de preparación previa. Ocupación = `[hora_partit, hora_partit + durada + marge_posterior)` (D-005, D-022). |
| P3 | ¿Quién es administrador? | **`Gestor` es administrador** y puede crear otros usuarios. | El seeder crea `Gestor` con el rol `admin` y la obligación de cambiar la contraseña (D-018). |
| P4 | Correo electrónico | **Con PHPMailer**, configurado en el backend. | Se crea un transporte de correo `phpmailer` para Laravel con la configuración SMTP en el `.env`. Laravel envía todo el correo (incluida la recuperación de contraseña) a través de él (D-023). |
| P5 | Partidos en casa sin regla | **El sábado por la mañana y, si no caben, el domingo por la mañana. Escoleta, nunca antes de las 10:00.** La pantalla de asignación muestra **sábado y domingo a la vez**. | Cada franja tiene un orden de relleno, `default_fill_order`: sábado mañana = 1, domingo mañana = 2, sábado tarde = sin valor, es decir, no se usa para los partidos sin regla. Las reglas admiten restricciones de ventana (`not_before`/`not_after`), y se crea la regla obligatoria «Escoleta no abans de les 10:00» (D-024). El calendario muestra los dos días en paralelo en todas las pantallas (D-025). |
| P6 | WhatsApp | **Publicar en los grupos de WhatsApp existentes y también en un canal de WhatsApp del club.** Se empieza con la vía A (manual asistida) y la vía B (pasarela) queda preparada. | Hay dos tipos de envío. Uno es **por equipo**, a su grupo, con un mensaje por partido. El otro es **del club**, al canal, con un único resumen del fin de semana y, opcionalmente, las imágenes de sábado y domingo. Véase el apartado 12 (D-014, D-027). |

---

## 1. Resumen funcional

La aplicación, íntegramente en catalán, permite al club de fútbol **Joventut Sant Pere Màrtir**:

1. Mantener **categorías** (con modalidad F7/F11 y duraciones configurables) y **equipos** (orden, color, escudo, destinos de WhatsApp y estado).
2. Organizar el trabajo por **temporadas** y **fines de semana de planificación**.
3. Introducir los **partidos** de cada fin de semana, marcados como en casa, fuera, descanso, aplazado, etc. Se ofrece una sugerencia de casa o fuera a partir del fin de semana anterior.
4. Generar una **propuesta automática** determinista para los partidos en casa, respetando las franjas, los bloqueos del campo, la capacidad (2×F7 o 1×F11) y las reglas preferentes.
5. Ajustar la propuesta en un **calendario visual con drag and drop**, que valida los movimientos en el frontend y los vuelve a validar en el backend.
6. Guardar **borradores versionados**, deshacer la última propuesta, comparar versiones y **confirmar** la programación cuando no haya conflictos críticos.
7. Previsualizar, editar y **enviar avisos de WhatsApp** de forma explícita, sin duplicados y con un historial de cada envío.
8. Generar **imágenes PNG de 1080 × 1920** para el sábado y para el domingo, con paginación si el contenido no cabe.
9. Gestionar **usuarios y roles** y mantener una **auditoría** de las acciones importantes.

---

## 2. Suposiciones realizadas

| Id | Suposición |
|----|------------|
| S1 | Hay **una única instalación local** (un campo) con tres áreas lógicas: `Camp 1`, `Camp 2` y `Camp complet`. El modelo admite más instalaciones en el futuro, pero la interfaz solo trabaja con una. |
| S2 | La unidad de planificación es el **fin de semana** (`matchdays`). La «jornada» de competición es un **atributo de cada partido** (`round_label`), porque cada categoría tiene su propio calendario federativo y la jornada 5 de los infantiles no tiene por qué coincidir con la jornada 5 de los alevines. |
| S3 | Las fechas y horas de los partidos se guardan en **hora local** (`DATE` + `TIME`, zona `Europe/Madrid`), sin conversión a UTC, para evitar errores en los cambios de horario. Las marcas de auditoría se guardan en UTC. |
| S4 | El **día de la semana no se guarda**: se calcula a partir de la fecha. |
| S5 | El **administrador tiene todos los permisos del gestor**. |
| S6 | La duración y el margen posterior se definen en la **categoría**. El equipo puede sobrescribirlos (los campos vacíos heredan los valores de la categoría) y cada partido conserva una copia de los valores efectivos, que se puede editar. Así, un cambio en la configuración no altera los partidos ya confirmados. No hay preparación previa (P2). |
| S6b | El horario operativo para colocar partidos manualmente es de 09:00 a 21:00 y **limita la hora de inicio**. Es un ajuste de la aplicación (`operating_hours`). La cuadrícula de 15 minutos es un ajuste global (`kickoff_step_min = 15`) que se aplica tanto a la propuesta automática como a los movimientos manuales. |
| S7 | El «horario preferente» del equipo se implementa como una **regla de programación con ámbito de equipo**. La ficha del equipo la muestra y la edita, pero solo hay un lugar donde se guardan las preferencias: `scheduling_rules`. |
| S8 | Un **movimiento manual** (drag and drop o edición) **bloquea automáticamente** el partido (`is_locked = true`) para que la siguiente propuesta no lo deshaga. Se puede desbloquear con un clic. |
| S9 | El estado `Descansa` se representa con una fila de partido sin rival ni horario. Sirve para la sugerencia de casa o fuera y para la agenda. |
| S10 | Para confirmar una programación, todos los partidos en casa activos (`Pendent`, `Proposat` o `Confirmat`) tienen que tener horario. Los partidos que no se jugarán se marcan como `Ajornat` o `Cancel·lat`. |
| S11 | Las imágenes incluyen los partidos con `show_in_image = true` y un estado distinto de `Cancel·lat` o `Descansa`. Los aplazados se muestran como «Ajornat» si el ajuste de imagen lo permite (desactivado por defecto). |
| S12 | El servidor de producción tiene acceso a Chromium headless para generar las imágenes. Si no lo tiene, hay una estrategia alternativa con GD (apartado 13). |

---

## 3. Casos límite identificados

**Planificación y campo**
- Un partido que empieza a las 13:00 y termina a las 14:15 es válido, porque la franja solo limita el inicio. Su ocupación sí bloquea el campo hasta las 14:15, de modo que un partido manual a las 14:00 entra en conflicto con él.
- La propuesta nunca coloca partidos fuera de las franjas. Un gestor que arrastra un partido al sábado a las 15:00 obtiene un aviso ámbar; a las 21:15 o a las 10:10, un error rojo.
- Tres equipos F7 con la misma preferencia a la misma hora: el tercero se desplaza al hueco válido más cercano y se genera un aviso ámbar.
- Una regla **obligatoria** que no se puede cumplir: el partido se coloca en la alternativa más cercana con un conflicto **rojo** («Regla obligatòria incomplerta»). El gestor puede moverlo o registrar una **excepción justificada** (`rule_override_reason`), que convierte el conflicto en ámbar y queda en la auditoría.
- Un partido F11 asignado a `Camp 1` o `Camp 2`: es un conflicto crítico de área.
- Un partido F7 asignado a `Camp complet`: se permite si el gestor lo quiere así (ocupa el campo entero) y se muestra un aviso ámbar.
- Un bloqueo parcial (solo `Camp 2`) y otro total.
- Un bloqueo creado **después** de confirmar la programación: se muestra un conflicto rojo y la programación pasa a «Pendent de revisar».
- Una excepción de fecha que elimina una franja (festivo) o que añade una (por ejemplo, un viernes por la tarde).
- Un partido que empieza justo cuando termina la ocupación de otro en la misma área (por ejemplo, uno acaba a las 11:15 y el otro empieza a las 11:15) es válido, porque los intervalos son semiabiertos `[a, b)`.
- Un intervalo mínimo entre partidos en la misma área: se aplica entre ocupaciones consecutivas y se suma al margen posterior. No se aplica entre `Camp 1` y `Camp 2`.
- Las duraciones no tienen que ser múltiplos de 15: solo la **hora de inicio** se ajusta a la cuadrícula. Un partido que termina a las 10:50 no deja libre el espacio hasta las 11:00, pero solo porque el siguiente inicio posible es a las 11:00.
- Dos partidos de Escoleta sin hueco en el sábado a partir de las 10:00 pasan al domingo a partir de las 10:00, nunca al sábado a las 09:00.

**Partidos**
- El mismo equipo dos veces en el mismo fin de semana (por ejemplo, la recuperación de un partido aplazado): genera un conflicto rojo, salvo que uno de los dos partidos esté `Ajornat` o `Cancel·lat`.
- Un equipo que juega fuera el sábado y en casa el domingo: se trata igual que el caso anterior.
- Una hora de convocatoria posterior a la hora del partido: es un conflicto rojo. Si no hay hora de convocatoria, se muestra un aviso ámbar en la imagen o el mensaje. No es un conflicto.
- Un partido fuera sin instalación o dirección: genera un aviso ámbar y bloquea el envío de su mensaje.
- La sugerencia de casa o fuera sin un fin de semana anterior (inicio de temporada) o con un descanso o aplazamiento la semana anterior: se busca el último partido jugado. Si no hay ninguno, no se propone nada.
- Un equipo desactivado que tiene partidos futuros: se mantiene y se muestra con una etiqueta.
- Un equipo eliminado con borrado lógico: los partidos históricos conservan el nombre.

**Concurrencia y procesos**
- Dos gestores mueven partidos a la vez: se aplica un bloqueo optimista con `lock_version` y se responde con `409` y el mensaje «La programació ha canviat. Recarrega-la».
- Un doble clic en «Confirma» o «Envia»: las operaciones son idempotentes y se protegen con transacciones y bloqueos de fila.
- Un cambio de horario **después** de enviar el WhatsApp: el mensaje se marca como `outdated` y se ofrece un «Envia l'actualització» explícito.
- El proveedor de WhatsApp no responde (timeout): el estado pasa a `unknown`. **No se reintenta automáticamente**, para evitar un posible duplicado, y requiere una revisión manual.
- La regeneración se ejecuta varias veces: no crea ni duplica partidos, solo reubica los que no están bloqueados.

**Imágenes**
- Demasiados partidos en un día: se paginan (1/2, 2/2) y se avisa al gestor. Hay un tamaño mínimo de letra.
- Nombres de rivales muy largos: se reducen en un solo nivel y, si siguen sin caber, se cortan con «…» y se avisa.
- Un día sin partidos: no se genera la imagen y se informa al gestor.
- Emojis y caracteres catalanes (l·l, accentos): se usan fuentes incluidas en el servidor.

---

## 4. Arquitectura propuesta

### 4.1 Visión general

```
┌──────────────────────── Navegador ────────────────────────┐
│ React 19 + TS (SPA, mismo origen)                         │
│  ├─ features/* (pantallas)                                │
│  ├─ domain/scheduling (validador TS puro) ◄─ fixtures JSON │
│  └─ api/ (TanStack Query, cookies Sanctum + XSRF)         │
└───────────────▲───────────────────────────────────────────┘
                │ HTTPS, JSON, cookies HttpOnly/Secure/SameSite
┌───────────────┴──────── Laravel 13 (PHP 8.5) ─────────────┐
│ Http: Controllers delgados · FormRequests · Resources     │
│       Policies/Gates · Middleware (CSP, headers, throttle)│
│ Application services (casos de uso, transacciones, audit) │
│   ScheduleService · ProposalService · ConfirmationService │
│   NotificationService · ImageService · MatchEntryService  │
│ Domain (PHP puro, sin Laravel) ◄─ mismas fixtures JSON    │
│   Scheduling: ConflictDetector · ProposalEngine           │
│   Messaging:  TemplateRenderer · WhatsAppProviderInterface│
│   Imaging:    LayoutPlanner · ScheduleImageRenderer       │
│ Infra: Eloquent · Queue (database) · Storage · Providers  │
└───────────────┬───────────────────────────────────────────┘
        MariaDB 11 / MySQL 8 · cola en la base de datos · Chromium headless
```

### 4.2 Decisiones principales

- **Monorepositorio y mismo origen.** El código React vive en `resources/frontend` y se compila con `laravel-vite-plugin`. Laravel sirve la SPA desde una ruta comodín. Ventajas: Sanctum funciona en modo *SPA stateful* con cookies de sesión y CSRF, sin tokens en `localStorage` y sin CORS.
- **Una capa de dominio sin dependencias del framework.** El motor de planificación, el detector de conflictos, el renderizador de plantillas y el planificador del diseño de las imágenes son clases PHP puras con objetos de valor inmutables. Se prueban sin base de datos y son deterministas.
- **Servicios de aplicación.** Orquestan Eloquent y el dominio, abren las transacciones, aplican el bloqueo optimista, escriben la auditoría y emiten los eventos.
- **Un validador duplicado en TypeScript, con fixtures compartidas.** El frontend necesita una respuesta inmediata mientras se arrastra una tarjeta. Para que las dos implementaciones no diverjan, se usa un único conjunto de casos en `tests/fixtures/scheduling/*.json` (entrada y conflictos esperados) que ejecutan PHPUnit y Vitest. El backend **siempre** valida con su propia implementación, y en caso de discrepancia prevalece el backend.
- **Eventos.** `ScheduleConfirmed`, `ScheduleChanged`, `MatchPlacementChanged` y `WhatsAppMessageSent` tienen listeners para la auditoría, para marcar los mensajes como desactualizados y para invalidar las imágenes.
- **Colas.** Se usa el driver `database`, que no requiere Redis. Los trabajos son `SendWhatsAppMessage` (uno por mensaje, `ShouldBeUnique` por la clave de idempotencia) y `GenerateScheduleImages`. Se ejecutan con systemd o Supervisor.

---

## 5. Modelo de datos

Convenciones: tablas en plural y en `snake_case`, `id` BIGINT sin signo, `timestamps()` en todas las tablas, `deleted_at` donde se indica, `created_by` y `updated_by` (FK a `users`, `nullOnDelete`) en las entidades editables. Los enums se guardan como `VARCHAR` con un *backed enum* de PHP, que es más portable entre MySQL y MariaDB y más fácil de migrar que `ENUM`. Hay restricciones `CHECK` donde MariaDB/MySQL las aplican.

### 5.1 Tablas

**Identidad y acceso**

| Tabla | Columnas clave | Índices / restricciones |
|---|---|---|
| `roles` | `id`, `code` (`admin`/`manager`), `name` | `UNIQUE(code)` |
| `users` | `id`, `username`, `name`, `email` NULL, `password` (Argon2id), `role_id` FK, `is_active`, `must_change_password`, `password_changed_at`, `last_login_at`, `remember_token`, `deleted_at` | `UNIQUE(username)`, `UNIQUE(email)` |
| `password_reset_tokens`, `sessions` | Tablas estándar de Laravel | — |

**Club**

| Tabla | Columnas clave | Índices / restricciones |
|---|---|---|
| `categories` | `id`, `name`, `slug`, `game_format` (`f7`/`f11`), `match_duration_min`, `post_min`, `min_gap_min`, `sort_order`, `is_active`, `deleted_at` | `UNIQUE(slug)`, `CHECK(duraciones ≥ 0)` |
| `teams` | `id`, `category_id` FK, `name`, `slug`, `game_format` NULL (hereda), `color` NULL (#RRGGBB), `match_duration_min`/`post_min` NULL (heredan), `logo_path` NULL, `sort_order`, `is_active`, `deleted_at` | `UNIQUE(slug)`, `INDEX(category_id, sort_order)` |
| `seasons` | `id`, `name` («2026-2027»), `starts_on`, `ends_on`, `is_current` | `UNIQUE(name)` |
| `matchdays` | `id`, `season_id` FK, `starts_on` (sábado), `ends_on` (domingo), `label` («Jornada 5» / «Cap de setmana 18-19 oct.») | `UNIQUE(season_id, starts_on)` |

**Instalaciones y disponibilidad**

| Tabla | Columnas clave | Índices / restricciones |
|---|---|---|
| `venues` | `id`, `name`, `address`, `municipality`, `maps_url`, `is_home` | `INDEX(is_home)` |
| `field_areas` | `id`, `venue_id` FK, `code` (`camp_1`, `camp_2`, `full`), `name`, `kind` (`half`/`full`), `sort_order` | `UNIQUE(venue_id, code)` — un área `full` ocupa todas las áreas `half` de la misma instalación |
| `availability_slots` | `id`, `venue_id` FK, `weekday` NULL (1–7 ISO), `specific_date` NULL, `label`, `opens_at` (primera hora de inicio), `last_kickoff_at` (última hora de inicio), `default_fill_order` NULL, `is_active` | `CHECK(weekday IS NOT NULL XOR specific_date IS NOT NULL)`, `CHECK(opens_at <= last_kickoff_at)`, `INDEX(venue_id, weekday)`, `INDEX(venue_id, specific_date)`. Si existen franjas con `specific_date` para una fecha, **sustituyen** a las semanales de esa fecha. Una fecha concreta sin ninguna franja activa equivale a un día cerrado |
| `field_blocks` | `id`, `venue_id` FK, `field_area_id` NULL (NULL = todo el campo), `starts_at`, `ends_at` (DATETIME local), `reason`, `deleted_at` | `INDEX(venue_id, starts_at, ends_at)`, `CHECK(starts_at < ends_at)` |

**Reglas**

| Tabla | Columnas clave | Índices / restricciones |
|---|---|---|
| `scheduling_rules` | `id`, `team_id` NULL, `category_id` NULL, `game_format` NULL, `weekday` NULL, `preferred_time` NULL, `not_before` NULL, `not_after` NULL (límites de la hora de inicio), `preferred_field_area_id` NULL, `priority` (INT, mayor = antes), `strength` (`mandatory`/`recommended`), `valid_from` NULL, `valid_to` NULL, `is_active`, `notes`, `deleted_at` | `CHECK(team_id IS NOT NULL OR category_id IS NOT NULL OR game_format IS NOT NULL)`, `CHECK(preferred_time IS NOT NULL OR not_before IS NOT NULL OR not_after IS NOT NULL)`, `INDEX(is_active, priority)`. Hay dos tipos de regla que se combinan. Las **de preferencia** (día y hora): se aplica una sola, la más específica (equipo > categoría > modalidad, después la de mayor prioridad y después la de `id` más bajo). Las **de ventana** (`not_before`/`not_after`): se aplican **todas** las vigentes. Las obligatorias filtran los candidatos y las recomendadas solo penalizan |

**Partidos y programación**

| Tabla | Columnas clave | Índices / restricciones |
|---|---|---|
| `matches` | `id`, `matchday_id` FK, `team_id` FK, `round_label` NULL, `opponent` NULL, `home_away` (`home`/`away`) NULL, `status` (`pending`, `proposed`, `confirmed`, `postponed`, `cancelled`, `rest`), `game_format`, `match_date` NULL, `kickoff_time` NULL, `call_time` NULL, `duration_min`, `post_min`, `field_area_id` NULL, `venue_id` NULL, `venue_name`, `address`, `municipality`, `maps_url`, `is_locked`, `placement_source` (`auto`/`manual`/NULL), `rule_override_reason` NULL, `notes`, `whatsapp_extra`, `show_in_image`, `lock_version`, `created_by`, `updated_by`, `deleted_at` | `INDEX(matchday_id, match_date, kickoff_time)`, `INDEX(team_id, matchday_id)`. **Sin `UNIQUE(matchday_id, team_id)`**: un equipo duplicado se detecta como conflicto para que se pueda registrar la recuperación de un partido aplazado |
| `schedules` | `id`, `matchday_id` FK, `venue_id` FK, `status` (`draft`, `proposed`, `confirmed`, `needs_review`), `lock_version`, `current_version_id` NULL, `confirmed_at`, `confirmed_by`, `confirmed_version_id` | `UNIQUE(matchday_id, venue_id)` |
| `schedule_versions` | `id`, `schedule_id` FK, `number`, `kind` (`proposal`, `manual_save`, `confirmation`, `undo`, `pre_proposal`), `snapshot` JSON (las posiciones de todos los partidos: fecha, hora, área, estado y bloqueo), `conflicts` JSON, `summary`, `created_by`, `created_at` | `UNIQUE(schedule_id, number)`. Es una tabla de solo inserción. Deshacer consiste en restaurar la versión anterior y crear una nueva versión `undo`. Comparar es calcular la diferencia entre dos `snapshot` |

**Comunicaciones e imágenes**

| Tabla | Columnas clave | Índices / restricciones |
|---|---|---|
| `whatsapp_destinations` | `id`, `scope` (`team`/`club`), `team_id` FK NULL (obligatorio si `scope = team`), `label`, `provider` (`manual`, `gateway`, `meta`, `twilio`, `log`), `destination_type` (`group`, `channel`, `phone`, `list`), `external_id` NULL (cifrado con el *cast* `encrypted`; es el identificador del grupo `…@g.us`, del canal `…@newsletter` o el teléfono; puede quedar vacío con el proveedor manual), `invite_url` NULL (enlace del grupo o del canal para abrirlo desde el proveedor manual), `include_images` (solo canal), `is_active`, `deleted_at` | `INDEX(scope, team_id, is_active)`, `CHECK(scope = 'club' OR team_id IS NOT NULL)` |
| `whatsapp_templates` | `id`, `code`, `name`, `kind` (`match`/`weekend_summary`), `applies_to` (`home`/`away`/`any`), `body`, `line_body` NULL (plantilla de cada línea de partido del resumen), `provider_template_name` NULL (plantilla aprobada por Meta), `provider_template_params` JSON NULL, `is_active` | `UNIQUE(code)` |
| `whatsapp_messages` | `id`, `schedule_id` FK, `match_id` FK NULL (NULL = resumen del canal), `whatsapp_destination_id` FK, `whatsapp_template_id` NULL, `schedule_version_id` FK, `provider`, `body`, `body_hash`, `idempotency_key`, `status` (`draft`, `queued`, `sending`, `sent`, `manual_sent`, `failed`, `unknown`, `outdated`, `discarded`), `attempts`, `provider_message_id`, `provider_response` JSON (saneado), `error_message`, `queued_at`, `sent_at`, `sent_by` | `UNIQUE(idempotency_key)` con la clave = `sha256(schedule_id · match_id|'summary' · destination_id · body_hash)`, `INDEX(schedule_id, status)`, `INDEX(match_id, status)` |
| `whatsapp_message_attachments` | `id`, `whatsapp_message_id` FK, `generated_image_id` FK, `sort_order` | `UNIQUE(whatsapp_message_id, generated_image_id)` — imágenes que se adjuntan a la publicación del canal |
| `generated_images` | `id`, `schedule_id` FK, `schedule_version_id` FK, `day` DATE, `page`, `total_pages`, `width`, `height`, `disk`, `path`, `checksum`, `renderer`, `warnings` JSON, `created_by` | `UNIQUE(schedule_version_id, day, page)` |

**Transversales**

| Tabla | Columnas clave | Índices / restricciones |
|---|---|---|
| `audit_logs` | `id`, `user_id` NULL, `event` (`schedule.confirmed`, `match.moved`, `auth.login_failed`…), `auditable_type`, `auditable_id`, `old_values` JSON, `new_values` JSON, `ip`, `user_agent`, `created_at` | Sin `updated_at`, solo inserción. `INDEX(auditable_type, auditable_id)`, `INDEX(event, created_at)`, `INDEX(user_id, created_at)` |
| `application_settings` | `id`, `key`, `value` JSON, `updated_by` | `UNIQUE(key)` — nombre del club, logo, colores, zona horaria, instalación local, `operating_hours` (09:00–21:00), `kickoff_step_min` (15), proveedor de WhatsApp por defecto y ajustes de imagen |
| `jobs`, `failed_jobs`, `job_batches`, `cache`, `cache_locks` | Tablas estándar de Laravel | — |

### 5.2 Diagrama textual de entidades

```
roles 1───* users
                │ (created_by / updated_by / confirmed_by / sent_by …)

categories 1───* teams 1───* whatsapp_destinations
     │              │
     │              ├──* scheduling_rules *──┐ (team_id | category_id | game_format)
     └──────────────┼────────────────────────┘
                    │
seasons 1───* matchdays 1───* matches *───1 teams
                   │             │  ├──0..1 field_areas *───1 venues
                   │             │  └──0..1 venues (instalación fuera)
                   │             └───* whatsapp_messages *───1 whatsapp_destinations
                   │                          │         └──0..1 whatsapp_templates
                   └───1 schedules 1───* schedule_versions ──┘ (versión enviada)
                              └───* generated_images ─── schedule_versions

venues 1───* field_areas
venues 1───* availability_slots   (semanales o de una fecha concreta)
venues 1───* field_blocks *──0..1 field_areas

audit_logs *───0..1 users   (polimórfica: auditable_type/id)
application_settings        (clave/valor)
```

### 5.3 Concurrencia

- `schedules.lock_version`: todas las operaciones que modifican la programación (mover, proponer, deshacer, guardar o confirmar) envían la `version` que conoce el cliente. Dentro de una transacción se ejecuta `SELECT … FOR UPDATE` sobre la fila. Si la versión no coincide, se responde `409 Conflict`. Si coincide, se incrementa.
- `matches.lock_version` cubre la edición de los datos del partido desde el panel lateral, por el mismo mecanismo.

---

## 6. Flujo de pantallas

```
Inicia la sessió ──► [Canvia la contrasenya] (obligatorio la primera vez)
       │
       ▼
Programació del cap de setmana  (pantalla principal)
  ├─ Selectores: Temporada · Cap de setmana (y jornada informativa)
  ├─ Pestaña «Partits»  → hoja rápida: una fila por equipo activo, con casa/fora/descansa
  │     sugerido y datos de los partidos fuera (panel lateral)
  ├─ Pestaña «Calendari» → DISSABTE y DIUMENGE uno al lado del otro en la misma pantalla.
  │     Cada día tiene un eje de horas de 09:00 a 21:00 (cuadrícula de 15 minutos) con las franjas
  │     sombreadas, y dentro de cada día las columnas Camp 1 | Camp 2 (las tarjetas F11 ocupan las dos).
  │     Las horas fuera de franja se pueden contraer.
  │     Debajo: «Partits a fora» de cada día, la bandeja «Partits sense programar» y el panel de conflictos
  ├─ Acciones: Genera una proposta · Desfés els canvis · Desa la programació ·
  │     Confirma la programació · Compara versions
  ├─ «Imatges» → genera / previsualiza / descarga PNG por día y página
  └─ «Avisos»  → previsualización editable por equipo y destino → confirmación → estado
                 del envío → «Reintenta els fallits» · historial
Equips          → lista ordenable (drag) · ficha (datos, horario preferente, WhatsApp, escudo)
Configuració    (solo admin)
  ├─ Categories (modalidad, duraciones, orden)
  ├─ Franges horàries (semanales + excepciones por fecha)
  ├─ Bloquejos del camp
  ├─ Regles de programació
  ├─ Plantilles de WhatsApp · Proveïdor de WhatsApp (estado, sin mostrar secretos)
  └─ Aparença (logo, colores, nombre del club)
Usuaris         (solo admin)
Auditoria       (solo admin, con filtros)
El meu perfil   → cambio de contraseña · Tanca la sessió
```

En una tableta, los dos días siguen uno al lado del otro con desplazamiento horizontal si hace falta. En el móvil, los dos días se apilan en la misma pantalla (sábado arriba, domingo debajo, con un índice fijo para saltar de uno a otro), **sin pestañas**, para que se pueda arrastrar un partido de un día al otro. El drag and drop funciona con *touch sensor* y un pequeño retraso. Además, cada tarjeta tiene la acción «Mou a…», un selector accesible de día, hora y campo que sirve tanto en el móvil como con el teclado.

---

## 7. Reglas del motor de planificación

### 7.1 Modelo temporal

- Los tiempos se representan en **minutos desde medianoche** (enteros) en hora local, por fecha.
- La ocupación de un partido en casa es `[kickoff, kickoff + duration + post)`, sin preparación previa (P2). El intervalo mínimo entre partidos se aplica como separación entre ocupaciones de la misma área.
- El kickoff es siempre múltiplo de `kickoff_step_min` (15). Una franja solo acota el **kickoff**: `opens_at ≤ kickoff ≤ last_kickoff_at`. El partido puede terminar después de `last_kickoff_at` (P1).
- Recursos por fecha: `H1` (Camp 1) y `H2` (Camp 2).
  - F7 en `camp_1` → ocupa {H1}. F7 en `camp_2` → ocupa {H2}. F7 en `full` → ocupa {H1, H2}.
  - F11 → siempre ocupa {H1, H2}. Si se asigna a otra área, se genera el conflicto crítico `F11_REQUIRES_FULL_FIELD`.
- Con este modelo, las cuatro reglas de capacidad del enunciado (2×F7 a la vez, no hay un tercer F7, F11 excluye F7, F11 excluye F11) se reducen a una sola comprobación: **ningún recurso tiene dos ocupaciones solapadas**. Es más simple y menos propenso a errores que tratar cada regla por separado. Aun así, el conflicto se **clasifica** con el código específico para mostrar el mensaje correcto.

### 7.2 Catálogo de conflictos

| Código | Severidad | Texto (ca) |
|---|---|---|
| `F7_CAPACITY_EXCEEDED` | crítico | «Hi ha massa partits de futbol 7 alhora» |
| `F11_OVERLAPS_F7` | crítico | «Un partit de futbol 11 coincideix amb futbol 7» |
| `F11_OVERLAPS_F11` | crítico | «Dos partits de futbol 11 coincideixen» |
| `F11_REQUIRES_FULL_FIELD` | crítico | «El futbol 11 necessita el camp complet» |
| `AREA_OVERLAP` | crítico | «El camp ja està ocupat» |
| `OUTSIDE_OPERATING_HOURS` | crítico | «L'hora ha d'estar entre les 09:00 i les 21:00» |
| `OFF_GRID` | crítico | «L'hora ha de ser en punt, i quart, i mitja o menys quart» |
| `OUTSIDE_SLOT` | aviso (solo se produce con colocaciones manuales) | «Fora de les franges habituals» |
| `FIELD_BLOCKED` | crítico | «Camp bloquejat: {motiu}» |
| `DUPLICATE_TEAM` | crítico | «L'equip té més d'un partit aquest cap de setmana» |
| `CALL_AFTER_KICKOFF` | crítico | «La convocatòria és posterior a l'hora del partit» |
| `MANDATORY_RULE_BROKEN` | crítico (ámbar si hay excepción) | «Regla obligatòria incomplerta» |
| `HOME_MATCH_UNSCHEDULED` | crítico al confirmar / ámbar en el borrador | «Partit a casa sense horari» |
| `RECOMMENDED_RULE_NOT_MET` | aviso | «No s'ha pogut respectar l'horari preferent» |
| `F7_ON_FULL_FIELD` | aviso | «Partit de futbol 7 ocupant el camp complet» |
| `AWAY_MISSING_DATA` | aviso | «Falten dades del partit a fora» |
| `MISSING_CALL_TIME` | aviso | «Falta l'hora de convocatòria» |

Colores en la interfaz: **rojo** = crítico; **ámbar** = aviso; **verde** = válido; **azul** = bloqueado o confirmado. Siempre se acompañan de un icono y un texto, nunca solo del color.

### 7.3 Algoritmo de propuesta (`ProposalEngine`)

Entrada: el `ProposalInput`, un objeto inmutable con los partidos, las franjas efectivas por fecha, los bloqueos, las reglas vigentes en la fecha y la configuración. Salida: el `ProposalResult`, con las posiciones, los partidos sin programar con su motivo y los avisos. El motor no tiene E/S.

1. **Selección.** Se toman los partidos `home_away = home` del fin de semana con estado `pending` o `proposed`. Los que tienen `is_locked`, `confirmed`, `postponed`, `cancelled` o `rest` no se mueven.
2. **Preocupación.** Se reservan los recursos de los partidos fijos (bloqueados o confirmados con posición) y los bloqueos del campo. Si los partidos fijos ya entran en conflicto entre ellos, se informa del conflicto, pero no se modifican.
3. **Candidatos.** Para cada partido se generan todas las posiciones válidas: (fecha, kickoff en la cuadrícula de 15 minutos con `opens_at ≤ kickoff ≤ last_kickoff_at` de alguna franja, área compatible). Una posición es válida si cumple las **reglas de ventana obligatorias** (por ejemplo, Escoleta ≥ 10:00), su ocupación no toca ningún bloqueo y los recursos están libres. Para un F11, el área es siempre `full`. Para un F7 se prueban `camp_1` y `camp_2`, y **nunca** `full` de forma automática.
4. **Regla efectiva.** La regla de preferencia se obtiene con el orden equipo > categoría > modalidad, después por prioridad y después por `id`. A esa regla se suman todas las reglas de ventana vigentes.
5. **Ordenación determinista de la cola:**
   1. reglas `mandatory`, por prioridad descendente;
   2. reglas `recommended`, por prioridad descendente;
   3. partidos sin regla;
   4. dentro de cada grupo, primero el que tiene **menos candidatos** (MRV), después `category.sort_order`, `team.sort_order` y `match.id`.
6. **Elección.** Para cada partido se elige el candidato con la **puntuación mínima**:
   - **Con regla de preferencia:** `score = 10 000·(fecha ≠ preferida) + |kickoff − hora_preferida| + 5·(área ≠ preferida) + penalización de las ventanas recomendadas no cumplidas`.
   - **Sin regla de preferencia (P5):** solo se consideran las franjas con `default_fill_order`. Gana la de menor orden (sábado mañana y después domingo mañana) y, dentro de la franja, la hora más temprana. Si no cabe en ninguna, el partido va a «Partits sense programar». No se usa el sábado por la tarde.
   - En caso de empate, gana la hora más temprana y después `camp_1` antes que `camp_2`. Después se reservan los recursos y se **recalculan los candidatos** de los partidos que quedan.
7. **Reparación limitada.** Si un partido no tiene candidatos, se intenta liberar **un** partido no bloqueado colocado previamente con una regla `recommended` o sin regla, moviéndolo a otro candidato propio, siempre que así se puedan colocar los dos. El número de intentos está acotado, de modo que el resultado sigue siendo determinista.
8. **Sin hueco.** El partido va a «Partits sense programar» con un motivo concreto: «No hi ha cap franja on hi càpiga la durada total (95 min)», «Tots els espais compatibles estan ocupats o bloquejats», etc.
9. **Avisos.** Para cada regla no cumplida se indica la distancia («Proposat 30 min més tard del preferent»). Las reglas obligatorias no cumplidas generan un conflicto crítico.
10. **Persistencia** (en `ProposalService`, dentro de una transacción con `FOR UPDATE`): se guarda la versión `pre_proposal`, se aplican las posiciones (estado `proposed`, `placement_source = auto`), se guarda la versión `proposal`, se incrementa `lock_version` y se registra en la auditoría.

Propiedades garantizadas por las pruebas:
- Con la misma entrada se obtiene la misma salida (prueba de ejecución doble y de entrada barajada).
- Es idempotente: ejecutarla dos veces seguidas produce la misma posición y no crea filas en `matches`.
- No mueve ningún partido bloqueado ni confirmado.

### 7.4 Validación de movimientos

En el calendario, el arrastre se ajusta a la cuadrícula de 15 minutos y no permite soltar un partido fuera de las 09:00–21:00. El backend rechaza igualmente cualquier hora fuera de la cuadrícula o del horario operativo.

`POST /api/schedules/{id}/moves/validate` recibe el partido, la fecha, la hora y el área, y devuelve los conflictos **sin guardar nada**. `POST /api/schedules/{id}/moves` vuelve a validar el movimiento y lo guarda. Los movimientos con conflictos críticos **se pueden guardar en el borrador** (el gestor puede querer resolverlos en dos pasos), pero quedan marcados en rojo y **la confirmación se rechaza** en el backend. El frontend calcula los conflictos en local mientras se arrastra la tarjeta (contorno verde, ámbar o rojo del destino) y, al soltarla, muestra el resultado del servidor.

---

## 8. API REST (borrador)

Todas las rutas están bajo `/api`, salvo `/sanctum/csrf-cookie`, y requieren autenticación excepto las de inicio de sesión y recuperación de contraseña.

```
POST   /auth/login · POST /auth/logout · GET /auth/me
POST   /auth/password/change · POST /auth/password/forgot · POST /auth/password/reset

GET|POST|PUT|DELETE   /users                     (admin)
GET|POST|PUT|DELETE   /categories                (escritura: admin)
GET|POST|PUT|DELETE   /teams  · PUT /teams/order · POST /teams/{id}/logo
GET|POST|PUT|DELETE   /seasons · /seasons/{id}/matchdays
GET|POST|PUT|DELETE   /matchdays/{id}/matches
POST                  /matchdays/{id}/matches/bulk          (hoja rápida)
GET                   /matchdays/{id}/home-away-suggestions
GET|POST|PUT|DELETE   /venues · /field-areas · /availability-slots · /field-blocks · /scheduling-rules

GET    /matchdays/{id}/schedule          (programación + conflictos + versión)
POST   /schedules/{id}/proposal          (genera; body: version)
POST   /schedules/{id}/undo
GET    /schedules/{id}/versions · GET /schedules/{id}/versions/{a}/compare/{b}
POST   /schedules/{id}/moves/validate · POST /schedules/{id}/moves
POST   /schedules/{id}/matches/{m}/lock · DELETE …/lock
POST   /schedules/{id}/save · POST /schedules/{id}/confirm

POST   /schedules/{id}/images            (encola la generación) · GET /schedules/{id}/images
GET    /generated-images/{id}/download   (ruta autorizada, no pública)

POST   /schedules/{id}/whatsapp/preview  (crea/actualiza borradores)
PUT    /whatsapp-messages/{id}           (edita el cuerpo del borrador)
POST   /schedules/{id}/whatsapp/send     (body: message_ids[], confirm: true, version)
POST   /schedules/{id}/whatsapp/retry-failed
POST   /whatsapp-messages/{id}/mark-sent (proveedor manual)
GET    /whatsapp-messages?matchday=…     (historial)
GET|POST|PUT|DELETE /whatsapp-templates · /teams/{id}/whatsapp-destinations   (admin)

GET    /audit-logs                       (admin)
GET|PUT /settings                        (admin)
```

Las respuestas usan API Resources. Los errores de validación devuelven `422` con mensajes en catalán (`lang/ca`). Las demás respuestas: `409` por concurrencia, `403` por autorización y `423` cuando se intenta confirmar con conflictos.

---

## 9. Estructura de directorios

```
GestPartits/
├── app/
│   ├── Domain/                       # PHP puro, sin dependencias de Laravel
│   │   ├── Scheduling/
│   │   │   ├── ValueObjects/         # TimeRange, Occupation, Placement, SlotWindow, GameFormat…
│   │   │   ├── Input/                # ProposalInput, MatchToPlace, RuleSpec, BlockSpec
│   │   │   ├── Conflicts/            # ConflictDetector, Conflict, ConflictCode, Severity
│   │   │   ├── Proposal/             # ProposalEngine, CandidateGenerator, RuleResolver, Scorer
│   │   │   └── Diff/                 # ScheduleDiff
│   │   ├── Messaging/
│   │   │   ├── WhatsAppProviderInterface.php
│   │   │   ├── OutgoingMessage.php · ProviderResult.php · ProviderCapabilities.php
│   │   │   └── TemplateRenderer.php
│   │   └── Imaging/
│   │       ├── ScheduleImageRenderer.php   # interfaz
│   │       ├── DayPoster.php · LayoutPlanner.php
│   ├── Infrastructure/
│   │   ├── WhatsApp/                 # MetaCloudProvider, TwilioProvider, ManualProvider, LogProvider
│   │   └── Imaging/                  # ChromiumRenderer, GdRenderer, FakeRenderer
│   ├── Services/                     # ScheduleService, ProposalService, ConfirmationService,
│   │                                 # NotificationService, ImageService, HomeAwaySuggester,
│   │                                 # AvailabilityResolver, AuditLogger, UploadService
│   ├── Enums/                        # MatchStatus, HomeAway, GameFormat, RuleStrength, …
│   ├── Models/
│   ├── Http/
│   │   ├── Controllers/Api/
│   │   ├── Requests/
│   │   ├── Resources/
│   │   └── Middleware/               # SecurityHeaders, ForcePasswordChange
│   ├── Policies/
│   ├── Jobs/                         # SendWhatsAppMessage, GenerateScheduleImages
│   ├── Events/ · Listeners/
│   └── Console/Commands/             # app:user:create, app:backup
├── config/gestpartits.php            # proveedores, renderizador, límites de subida
├── database/{migrations,seeders,factories}
├── lang/ca/                          # validation.php, auth.php, passwords.php, messages.php
├── resources/
│   ├── views/app.blade.php           # contenedor de la SPA
│   ├── views/images/day-poster.blade.php
│   ├── fonts/                        # fuentes con licencia libre para las imágenes
│   └── frontend/
│       ├── src/
│       │   ├── app/                  # router, providers, layout
│       │   ├── api/                  # cliente HTTP, tipos, hooks de TanStack Query
│       │   ├── domain/scheduling/    # validador TS puro (espejo del PHP)
│       │   ├── features/{auth,schedule,matches,teams,settings,rules,messaging,images,users,audit}/
│       │   ├── components/ui/        # componentes de shadcn/ui
│       │   ├── i18n/ca.ts            # todos los textos visibles
│       │   └── lib/
│       └── tests/
├── tests/
│   ├── Unit/Domain/…  · Feature/Api/…  · Feature/Security/…
│   └── fixtures/scheduling/*.json    # compartidas por PHPUnit y Vitest
├── deploy/                           # nginx.conf, apache.conf, systemd/*.service, cron, backup.sh
├── docs/                             # este documento, DECISIONS.md, apuntes de la API
├── .env.example · README.md · package.json · vite.config.ts · composer.json
```

---

## 10. Dependencias seleccionadas

**Backend (Composer)**

| Paquete | Motivo |
|---|---|
| `laravel/framework` ^13 | Requiere PHP ≥ 8.3 y es compatible con PHP 8.5 (en esta máquina hay PHP 8.5.11) |
| `laravel/sanctum` | Autenticación de la SPA con cookies |
| `chrome-php/chrome` | Controla Chromium headless desde PHP, **sin Node en tiempo de ejecución**. Lo usa el renderizador de imágenes principal |
| `intervention/image` ^3 | Recodifica los logos subidos (elimina cualquier carga incrustada) y sirve para el renderizador alternativo con GD |
| `phpmailer/phpmailer` ^7 | Envío de correo SMTP (P4), registrado como transporte `phpmailer` de Laravel para que las notificaciones y la recuperación de contraseña lo usen sin cambios |
| *(dev)* `phpunit/phpunit`, `laravel/pint`, `larastan/larastan` | Pruebas, estilo y análisis estático |

No se usan SDKs de Twilio ni de Meta: con `Http::` de Laravel hay suficiente, porque son dos o tres llamadas.

**Frontend (npm)**

| Paquete | Motivo |
|---|---|
| `react`, `react-dom` 19, `typescript`, `vite`, `laravel-vite-plugin`, `@vitejs/plugin-react` | Base |
| `@dnd-kit/core`, `@dnd-kit/sortable`, `@dnd-kit/modifiers`, `@dnd-kit/utilities` | Drag and drop del calendario y ordenación de los equipos |
| `tailwindcss` 4 + componentes de **shadcn/ui** (Radix UI), `lucide-react`, `sonner` | Interfaz accesible e iconos. Los componentes se copian al repositorio |
| `@tanstack/react-query` | Estado del servidor, invalidación y estado optimista con *rollback* |
| `react-router` | Rutas |
| `react-hook-form`, `zod`, `@hookform/resolvers` | Formularios con validación del cliente (el servidor sigue siendo la autoridad) |
| *(dev)* `vitest`, `@testing-library/react`, `jsdom`, `eslint`, `prettier` | Pruebas y estilo |

Se descarta i18next: solo hay un idioma, y un diccionario `ca.ts` tipado basta. Las fechas se formatean con `Intl.DateTimeFormat('ca')`, sin librería de fechas.

Las versiones exactas se fijarán en `composer.lock` y `package-lock.json` al empezar la Fase 2.

---

## 11. Seguridad (diseño, se implementa en las fases 2 a 7)

- **Contraseñas:** se guardan con Argon2id (`HASHING_DRIVER=argon2id`). El usuario inicial `Gestor` (rol `admin`, puede crear más usuarios) se crea con `INITIAL_USER_USERNAME`/`INITIAL_USER_PASSWORD` del `.env` local, y la contraseña **no aparece en ningún archivo del repositorio**. `must_change_password = true` obliga a cambiarla en el primer acceso, con el middleware `ForcePasswordChange` (todo responde `403` excepto el cambio de contraseña y el cierre de sesión). Se aplica una política de contraseñas mínima (`Password::min(12)->uncompromised()` es opcional, porque requiere acceso a internet).
- **Inicio de sesión:** `RateLimiter` por usuario e IP (5 intentos por minuto y bloqueo progresivo). Los fallos se registran en `audit_logs` sin la contraseña. La sesión se regenera al iniciar sesión y se invalida al cerrarla.
- **Cookies:** `SESSION_SECURE_COOKIE=true`, `http_only`, `same_site=lax`, `SESSION_ENCRYPT=true`. Protección CSRF de Sanctum (XSRF-TOKEN y la cabecera `X-XSRF-TOKEN`).
- **Cabeceras:** el middleware `SecurityHeaders` añade una CSP estricta (`default-src 'self'`, `script-src 'self'` con nonce de Vite, `img-src 'self' data: blob:`, `frame-ancestors 'none'`, `object-src 'none'`), `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy` y HSTS en producción.
- **XSS:** React escapa el contenido por defecto y está **prohibido** `dangerouslySetInnerHTML` (regla de ESLint). La plantilla Blade de las imágenes solo usa `{{ }}`.
- **Autorización:** `Gate::before` concede todos los permisos al administrador, hay *policies* por modelo y pruebas de autorización para **cada** ruta de escritura.
- **Archivos subidos:** solo PNG, JPEG o WebP, con un máximo de 2 MB y dimensiones acotadas. El tipo MIME se comprueba con `finfo`. La imagen se **recodifica** y se guarda con un nombre aleatorio (ULID) en un disco privado. SVG no se admite (riesgo de XSS). El directorio de almacenamiento no ejecuta PHP (configuración de Nginx).
- **Secretos:** solo en el `.env` (`WHATSAPP_META_TOKEN`, `TWILIO_AUTH_TOKEN`…). `.env` está en `.gitignore`. Los identificadores de destino se cifran con `APP_KEY`. Un *log processor* enmascara tokens, contraseñas y teléfonos.
- **Integridad:** transacciones con `FOR UPDATE` para proponer, mover, confirmar y enviar. La confirmación es idempotente. El envío se protege con la clave de idempotencia única y la transición atómica `UPDATE … SET status='sending' WHERE id=? AND status IN ('draft','failed')`.
- **Auditoría:** se registran los movimientos, las propuestas, las confirmaciones, los envíos, los cambios de configuración y de usuarios, y los accesos fallidos.

---

## 12. Estrategia para WhatsApp

**Realidad técnica, a revisar en la instalación:**
- La **WhatsApp Business Platform (Cloud API) de Meta** envía mensajes a **números de teléfono** que hayan aceptado recibirlos (*opt-in*). Los mensajes que inicia la empresa fuera de la ventana de 24 horas deben usar **plantillas aprobadas por Meta**, con variables. Por eso **el texto libre editable no se puede enviar tal cual**: con este proveedor solo se editan las variables. La publicación en grupos ordinarios de WhatsApp no está garantizada por la API estándar y no debe darse por hecha. Si Meta la ofrece para la cuenta del club, se puede añadir como capacidad del proveedor.
- **Twilio** tiene las mismas restricciones de fondo, porque actúa como intermediario de la API de Meta.
- Las soluciones no oficiales que automatizan WhatsApp Web incumplen los términos de servicio y pueden provocar el bloqueo del número. **No se implementan.**

**Requisito del club (P6): publicar en los grupos existentes.** Hay tres vías posibles, que dependen de una decisión del club:

| Vía | Cómo funciona | Ventajas | Inconvenientes |
|---|---|---|---|
| **A. Manual asistida** (`ManualProvider`) | Para cada grupo, un clic en «Envia al grup» abre WhatsApp (`wa.me/?text=…`) con el texto ya escrito. El gestor elige el grupo, pulsa enviar y la aplicación lo registra como enviado | Funciona hoy, sin riesgos ni costes, con el número personal del gestor | Un clic y una selección de grupo por equipo, unos 10 a 20 por fin de semana. La aplicación no puede confirmar que se ha enviado |
| **B. Pasarela no oficial** (`WebGatewayProvider`) | Un número **dedicado del club** se vincula como dispositivo de WhatsApp Web a una pasarela, autoalojada (por ejemplo, un servicio Node basado en Baileys o whatsapp-web.js) o comercial. La aplicación llama a su API HTTP con el identificador del grupo (`…@g.us`) | Envío automático real a los grupos existentes (el número dedicado tiene que ser miembro) | **Incumple los términos de WhatsApp**, con riesgo de bloqueo del número. Tiene un mantenimiento frágil (WhatsApp cambia el protocolo). Si es un servicio comercial, implica datos de terceros y un coste |
| **C. API oficial de Meta** | Envía a números individuales con plantillas aprobadas | Oficial y estable | **No** publica en grupos existentes. No cumple el requisito |

**Decisión del club (2026-10-16):** se empieza con **A**. **B** queda implementada como proveedor opcional, desactivado por defecto: se activa con `WHATSAPP_GATEWAY_ENABLED=true` y requiere un número dedicado y la aceptación explícita del riesgo, registrada por un administrador. **C** queda como adaptador opcional para avisos a números concretos. La interfaz y el flujo de envío (previsualización, confirmación, idempotencia e historial) son los mismos en las tres vías.

**Destinos:**

| Destino | Ámbito | Contenido | Vía A (manual) | Vía B (pasarela) |
|---|---|---|---|---|
| **Grupo del equipo** | Un grupo o más por equipo | Un mensaje por partido (plantilla de casa o de fuera) | «Envia al grup» abre WhatsApp (`wa.me/?text=…`) con el texto escrito y el gestor elige el grupo | Envío al identificador `…@g.us` |
| **Canal del club** | Uno o más canales del club | **Un único resumen del fin de semana** (plantilla `weekend_summary`: cabecera, una línea por partido ordenada por día y hora, cierre), con las imágenes de sábado y domingo adjuntas si se quiere | «Copia el resum» y «Descarga les imatges», y después «Obre el canal» con `invite_url`. El gestor lo pega y lo publica, porque WhatsApp no deja abrir un canal con el texto ya escrito | Publicación en el identificador `…@newsletter`, si la pasarela permite publicar en canales: hay que comprobarlo al elegirla. El número dedicado tiene que ser administrador del canal |

Si el club usa una **lista de difusión** en lugar de un canal, el mismo destino de ámbito club sirve con `destination_type = list`, y el contenido es el mismo resumen.

**Orden de envío recomendado:** primero el resumen al canal y después los grupos de cada equipo, aunque el gestor puede enviarlos por separado. El canal solo se puede publicar si la programación está **confirmada** y, cuando lleva imágenes adjuntas, si las imágenes de la versión confirmada ya están generadas.

**Diseño:**

```php
interface WhatsAppProviderInterface
{
    public function key(): string;                          // 'manual' | 'meta' | 'twilio' | 'log'
    public function capabilities(): ProviderCapabilities;   // supportsGroups, supportsFreeText,
                                                            // requiresTemplate, isManual
    public function validateDestination(DestinationType $type, string $externalId): ?string;
    public function send(OutgoingMessage $message): ProviderResult; // sent|failed|unknown + raw saneado
}
```

- `ManualProvider` **(por defecto)**: no envía nada. La interfaz muestra el texto final con los botones «Copia el missatge» y «Obre WhatsApp» (`https://wa.me/?text=…`, que permite elegir el grupo) o «Obre el canal» (`invite_url`), y, después, «Marca com a enviat». Queda registrado quién lo marcó y cuándo.
- `WebGatewayProvider` (vía B, desactivado por defecto): cliente HTTP de una pasarela configurada en el `.env` (`WHATSAPP_GATEWAY_URL`, `WHATSAPP_GATEWAY_TOKEN`). La pasarela concreta se elige al activarla; el adaptador declara qué admite (`supportsGroups`, `supportsChannels`, `supportsMedia`). Los destinos que la pasarela no admite pasan automáticamente al proveedor manual.
- `MetaCloudProvider`: para destinos de tipo `phone`, con una plantilla aprobada (`provider_template_name`) a la que se le pasan las variables.
- `TwilioProvider`: para destinos de tipo `phone` (`whatsapp:+34…`).
- `LogProvider`: para desarrollo y pruebas. Escribe en el log y simula estados configurables.
- Un `WhatsAppProviderRegistry` elige el proveedor según `destination.provider`. La lógica de negocio (`NotificationService`) solo conoce la interfaz.

**Flujo de envío:** se previsualiza (se crean los mensajes `draft` con el cuerpo renderizado), se edita, se abre el diálogo de confirmación con el recuento de mensajes y destinos, se envía (`queued` y un trabajo por mensaje) y se pasa a `sending`, que acaba en `sent`, `failed` o `unknown`. «Reintenta» solo actúa sobre `failed`. Guardar un borrador **nunca** llama a `NotificationService`, y hay una prueba que lo verifica con `Queue::fake()` y `Bus::assertNothingDispatched()`.

**Plantillas:** se usa una sintaxis `{{variable}}` con una lista cerrada de variables (`equip`, `rival`, `data`, `dia`, `hora_convocatoria`, `hora_partit`, `installacio`, `adreca`, `municipi`, `mapa`, `observacions`). Las variables se sustituyen sin evaluar código. Si una plantilla usa una variable desconocida, se rechaza al guardarla. La plantilla del resumen (`weekend_summary`) tiene además `{{cap_de_setmana}}`, `{{partits_dissabte}}` y `{{partits_diumenge}}`, que se generan aplicando `line_body` a cada partido (con sus variables y `{{icona}}` 🏠/🚌). Por defecto:

```
⚽ Partits del cap de setmana · {{cap_de_setmana}}

📅 DISSABTE
{{partits_dissabte}}

📅 DIUMENGE
{{partits_diumenge}}

Som-hi, Joventut! 🔵⚪
```

Con `line_body` = `{{icona}} {{hora_partit}} · {{equip}} - {{rival}}`. En los partidos fuera se añade ` ({{municipi}})`.

---

## 13. Estrategia para las imágenes

- **Interfaz:** `ScheduleImageRenderer::render(DayPoster $poster): RenderedImage` (bytes PNG + metadatos). `ImageService` y `GenerateScheduleImages` dependen únicamente de la interfaz, y el renderizador se elige en `config/gestpartits.php`.
- **Paginación en el dominio, no en el navegador.** `LayoutPlanner` (PHP puro) calcula cuántas filas caben a partir de unas alturas fijas: cabecera de 360 px, pie de 140 px, fila de 150 px con un tamaño mínimo de letra de 34 px y fila compacta de 120 px con un mínimo de 30 px. Con este cálculo decide entre el modo normal, el compacto o la paginación, y genera los avisos («El diumenge no cap en una imatge: s'han generat 2 pàgines»). Es determinista y se puede probar sin Chromium.
- **`ChromiumRenderer` (principal):** renderiza una vista Blade (`day-poster.blade.php`) con CSS propio, fuentes locales (Inter u otra de licencia libre, más Noto Color Emoji para los iconos) y el logo en `data:` URI. Usa un viewport de **1080 × 1920** y `deviceScaleFactor: 1`, y genera la captura en PNG. No accede a la red (`--disable-network`). Después de renderizar, se comprueba por JavaScript que no hay desbordamiento. Si lo hay, se avisa como medida de seguridad.
- **`GdRenderer` (alternativa):** dibuja con Intervention Image y GD el mismo `DayPoster` con un diseño más sobrio, para servidores sin Chromium.
- **`FakeRenderer`:** se usa en las pruebas. Genera un PNG de 1080 × 1920 de un solo color, y las pruebas comprueban las dimensiones, el número de imágenes por día y la paginación.
- **Diseño:** fondo azul del club con un degradado suave, tarjetas blancas, la hora destacada y un icono 🏠 o 🚌 con el texto «A casa» o «A fora». Los partidos fuera muestran el municipio o la instalación. El orden es cronológico, con separadores «Matí» y «Tarda». El escudo y el nombre del club van en la cabecera.
- **Almacenamiento:** disco privado `storage/app/private/images/{schedule}/{version}/`. La descarga se hace a través de una ruta autorizada. Se pueden regenerar en cualquier momento. Las imágenes de versiones antiguas se purgan con una tarea programada.

---

## 14. Riesgos técnicos

| Riesgo | Impacto | Mitigación |
|---|---|---|
| Publicación en grupos existentes sin API oficial | Riesgo de bloqueo del número (vía B) o un paso manual por grupo (vía A) | Decisión explícita del club. Vía A por defecto y vía B aislada en su propio proveedor |
| Validador duplicado TS/PHP | Diferencias entre el frontend y el backend | Fixtures JSON compartidas y ejecutadas en las dos suites. El backend tiene la última palabra |
| Chromium en Debian (unos 300 MB, consumo de memoria, fuentes) | Las imágenes no se generan | Instrucciones de instalación (`chromium`, `fonts-noto-color-emoji`), un trabajo con *timeout* y reintento, y el renderizador GD como alternativa |
| Paquetes todavía sin soporte oficial para PHP 8.5 | Errores de instalación | Se comprobará en la Fase 2. Hay pocas dependencias |
| Entorno local: hay PHP 8.5.11, Composer 2.10, MariaDB 11.8, Chromium y Node 24 (con nvm). **Faltan las extensiones PHP `gd` e `intl`** | Fallarán la recodificación de los logos, el renderizador GD y algunas funciones de formato de Laravel | `sudo apt install php8.5-gd php8.5-intl fonts-noto-color-emoji` |
| Drag and drop en el móvil | Uso incómodo | `TouchSensor` con retraso y la alternativa «Mou a…» |
| Calidad del algoritmo voraz | Partidos sin colocar que se podrían haber colocado | MRV, reparación limitada y motivos explícitos. El gestor siempre puede ajustar manualmente |
| Concurrencia entre gestores | Programaciones sobrescritas | `lock_version`, `409` y recarga guiada |

---

## 15. Plan de pruebas (resumen)

Las 22 pruebas mínimas del enunciado se distribuyen así:
- **Unitarias de dominio** (PHPUnit y Vitest, con fixtures compartidas): casos 1–13 y 20. Además: determinismo, idempotencia, intervalos semiabiertos, cuadrícula de 15 minutos, horario operativo, orden de relleno sábado → domingo, Escoleta ≥ 10:00 y reparación limitada.
- **Funcionales de la API** (PHPUnit con la base de datos de prueba): casos 14–17, confirmación duplicada, `409` por concurrencia, partido fuera sin datos y `ForcePasswordChange`.
- **Seguridad**: casos 18 y 19, límite de intentos, cabeceras CSP y HSTS, archivos SVG o PHP renombrados como `.png`, y logs sin secretos.
- **Imágenes**: caso 21 con `FakeRenderer` (dimensiones, dos días, paginación) y una prueba opcional con Chromium marcada como `@group chromium`.
- **Idioma** (caso 22): una prueba que comprueba que `lang/ca` contiene todas las claves de validación usadas, otra en Vitest que garantiza que los componentes solo usan claves de `ca.ts` (y que en la interfaz no hay literales en inglés detectables, con una regla de ESLint para textos JSX) y otra que comprueba los nombres de los días y los estados en catalán.

---

## 16. Plan de las fases siguientes

| Fase | Contenido |
|---|---|
| 2 | Esqueleto de Laravel y React, Sanctum, roles, `ForcePasswordChange`, migraciones de **todas** las tablas, modelos, enums, seeders, `lang/ca`, layout y página de inicio de sesión |
| 3 | CRUD de categorías, equipos (ordenación y escudo), temporadas, fines de semana, partidos (hoja rápida y sugerencia casa/fuera), franjas, bloqueos y reglas |
| 4 | Dominio `Scheduling` (detector, motor y diferencias), servicios, versiones, bloqueo y fixtures con pruebas |
| 5 | Calendario con dnd-kit, validador TS, panel de edición, panel de conflictos y diseño responsive |
| 6 | Plantillas, proveedores de WhatsApp, previsualización, envío y reintento, y renderizadores de imágenes |
| 7 | Revisión de seguridad, CSP, auditoría, README, despliegue en Debian (Nginx/Apache, systemd, cron) y copias de seguridad |
