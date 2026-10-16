# Registro de decisiones

Formato: `D-NNN · fecha · estado` (Propuesta / Aceptada / Sustituida). Detalle en [fase-1-analisi-arquitectura.md](fase-1-analisi-arquitectura.md).

| Id | Fecha | Estado | Decisión | Motivo |
|---|---|---|---|---|
| D-001 | 2026-10-15 | Propuesta | Monorepositorio Laravel 13 + SPA React servida desde el mismo origen (`resources/frontend`) | Sanctum con cookies y CSRF, sin CORS ni tokens en el navegador |
| D-002 | 2026-10-15 | Propuesta | Capa `app/Domain` en PHP puro para la planificación, los mensajes y el diseño de las imágenes | Determinismo y pruebas sin infraestructura |
| D-003 | 2026-10-15 | Propuesta | Validador de conflictos duplicado en TypeScript, con fixtures JSON compartidas | Respuesta inmediata en el drag and drop sin perder coherencia; el backend es la autoridad |
| D-004 | 2026-10-15 | Propuesta | La capacidad del campo se modela con dos recursos (H1, H2): F7 ocupa uno y F11 ocupa los dos | Las cuatro reglas de capacidad se reducen a una sola comprobación de solapamiento |
| D-005 | 2026-10-15 | Sustituida por D-022 | Ocupación = `[kickoff − prep, kickoff + durada + marge)` | — |
| D-006 | 2026-10-15 | Sustituida por D-021 | Las franjas tienen `opens_at`, `last_kickoff_at` y `closes_at` | — |
| D-007 | 2026-10-15 | Propuesta | El fin de semana (`matchdays`) es la unidad de planificación; la jornada es `matches.round_label` | Cada categoría tiene su propio calendario federativo |
| D-008 | 2026-10-15 | Propuesta | Fechas y horas de los partidos en hora local (`DATE` + `TIME`) | Evita errores en los cambios de horario |
| D-009 | 2026-10-15 | Propuesta | Horario preferente del equipo = regla de programación con ámbito de equipo | Un solo lugar para guardar las preferencias |
| D-010 | 2026-10-15 | Propuesta | Un movimiento manual bloquea el partido automáticamente | Las regeneraciones no deshacen el trabajo del gestor |
| D-011 | 2026-10-15 | Propuesta | Algoritmo voraz determinista con MRV y reparación limitada | Simple, explicable y suficiente para unos 10–30 partidos por fin de semana |
| D-012 | 2026-10-15 | Propuesta | Versiones de la programación como instantáneas JSON de solo inserción | Deshacer y comparar sin lógica compleja |
| D-013 | 2026-10-15 | Propuesta | Bloqueo optimista (`lock_version`) + `SELECT … FOR UPDATE` + `409` | Dos gestores no se sobrescriben la programación |
| D-014 | 2026-10-16 | Aceptada (P6) | Publicar en los grupos existentes. Vía A, manual asistida, por defecto; vía B, pasarela no oficial con un número dedicado, implementada pero desactivada (`WHATSAPP_GATEWAY_ENABLED`) y con aceptación explícita del riesgo; Meta/Twilio solo para números | La API oficial no publica en grupos existentes; decisión del club |
| D-015 | 2026-10-15 | Propuesta | Idempotencia de los envíos: clave única y transición atómica de estado; sin reintento automático en el estado `unknown` | Evitar mensajes duplicados |
| D-016 | 2026-10-15 | Propuesta | Imágenes: `LayoutPlanner` en el dominio + `ChromiumRenderer` (chrome-php, sin Node) + `GdRenderer` como alternativa + `FakeRenderer` en las pruebas | Diseño HTML/CSS de calidad sin acoplar los controladores; paginación probada |
| D-017 | 2026-10-15 | Propuesta | Enums como `VARCHAR` + PHP *backed enums* | Portabilidad entre MySQL y MariaDB y migraciones más sencillas |
| D-018 | 2026-10-16 | Aceptada (P3) | El usuario inicial `Gestor` se crea desde el `.env` con el rol `admin` y `must_change_password`; puede crear más usuarios | Respuesta del club |
| D-019 | 2026-10-15 | Propuesta | Sin i18next ni librería de fechas: diccionario `ca.ts` + `Intl` | Hay un solo idioma; menos dependencias |
| D-020 | 2026-10-15 | Propuesta | Logos: PNG, JPEG o WebP recodificados; SVG no admitido | Elimina cargas incrustadas y el riesgo de XSS |
| D-021 | 2026-10-16 | Aceptada (P1) | Las franjas solo limitan el kickoff (`opens_at`…`last_kickoff_at`). El horario operativo manual va de 09:00 a 21:00 y limita la hora de inicio (21:00 es el último kickoff posible): fuera de una franja da aviso ámbar y fuera del horario operativo, error rojo | Respuesta del club: las 13:00 son la última hora de inicio |
| D-022 | 2026-10-16 | Aceptada (P2) | Sin preparación previa: ocupación = `[kickoff, kickoff + durada + marge_posterior)`, intervalo semiabierto | Respuesta del club |
| D-023 | 2026-10-16 | Aceptada (P4) | Correo con PHPMailer como transporte `phpmailer` de Laravel, configurado con SMTP en el `.env` | Respuesta del club; se mantiene la API de correo y notificaciones de Laravel |
| D-024 | 2026-10-16 | Aceptada (P5) | Partidos sin regla: franjas con `default_fill_order` (sábado mañana → domingo mañana). Reglas de ventana `not_before`/`not_after`, con la regla obligatoria «Escoleta ≥ 10:00» | Respuesta del club |
| D-025 | 2026-10-16 | Aceptada (P5) | El calendario muestra sábado y domingo a la vez en todos los tamaños (apilados en el móvil, sin pestañas) | Respuesta del club; permite arrastrar entre días |
| D-026 | 2026-10-16 | Aceptada (P1) | Cuadrícula fija de 15 minutos para el kickoff, en los modos automático y manual (ajuste global `kickoff_step_min`) | Respuesta del club |
| D-027 | 2026-10-16 | Aceptada (P6) | Además de los grupos de los equipos, se publica en un canal de WhatsApp del club: destino de ámbito `club`, un único resumen del fin de semana (plantilla `weekend_summary`) e imágenes de sábado y domingo adjuntas opcionales; solo con la programación confirmada. Las listas de difusión usan el mismo mecanismo | Petición del club; un canal es una difusión de uno a muchos, así que no tiene sentido enviar un mensaje por partido |
| D-028 | 2026-10-16 | Aceptada | El modelo de la tabla `matches` es `GameMatch` | `match` es una palabra reservada de PHP 8 |
| D-029 | 2026-10-16 | Aceptada | Pruebas sobre MariaDB (`gestpartits_test`), no SQLite | Las mismas restricciones `CHECK`, el mismo bloqueo `FOR UPDATE` y el mismo JSON que en producción; la extensión SQLite no está instalada |
| D-030 | 2026-10-16 | Aceptada | La CSP permite `'unsafe-inline'` solo en `style-src`; los scripts llevan nonce | Los componentes Radix y Sonner inyectan `<style>`; los scripts siguen estrictos |
| D-031 | 2026-10-16 | Aceptada | Rutas de la SPA en catalán (`/inicia-sessio`, `/usuaris`…); Laravel sirve la SPA con una ruta comodín | Interfaz íntegramente en catalán |
| D-032 | 2026-10-16 | Aceptada | `app_timezone` = UTC para las marcas de tiempo; `CLUB_TIMEZONE` (Europe/Madrid) para las fechas y horas de los partidos | Coherente con S3 |
| D-033 | 2026-10-16 | Aceptada | Versiones fijadas en los lockfiles: Laravel 13.36, Sanctum 4.3, PHPMailer 7.1, React 19.3, React Router 8.4, TanStack Query 5, Vite 8, Tailwind 4, TypeScript 6, Zod 4, Vitest 5 | Versiones estables actuales al iniciar la Fase 2 |
| D-034 | 2026-10-16 | Aceptada | Gestor (y admin): equipos, temporadas, fines de semana y partidos. Solo admin: categorías, franjas, bloqueos y reglas | El apartado 4 permite al gestor gestionar los equipos; el apartado 17 reserva la configuración al administrador |
| D-035 | 2026-10-16 | Aceptada | Se rechazan al guardar: hora fuera de la cuadrícula o del horario operativo (partidos en casa), F11 fuera del campo completo, colocación incompleta, convocatoria posterior al partido y área en un partido fuera. El resto de conflictos (solapamientos, franjas, reglas, equipo duplicado) los detecta el motor (Fase 4) | Son datos imposibles; los conflictos de planificación se tienen que poder guardar como borrador |
| D-036 | 2026-10-16 | Aceptada | Una regla tiene un único ámbito (equipo, categoría o modalidad) | Resolución de prioridad simple y predecible |
| D-037 | 2026-10-16 | Aceptada | Los escudos se recodifican a PNG (Intervention Image + GD) con nombre ULID en el disco privado y se sirven por una ruta autenticada | Elimina las cargas incrustadas y evita la ejecución y el acceso públicos |
| D-038 | 2026-10-16 | Aceptada | Horas con desplegables de 15 minutos y campos de texto `hh:mm` en lugar de `<input type="time">` | Formato de 24 h independiente del idioma del navegador; la cuadrícula se aplica también en la interfaz |
| D-039 | 2026-10-16 | Aceptada | Páginas de la SPA con `React.lazy` | Reduce el paquete inicial a la mitad |
| D-040 | 2026-10-16 | Aceptada | La unidad de planificación (tabla `matchdays`) se llama **«Jornada»** en la interfaz (antes «Cap de setmana»). El campo `matches.round_label` se muestra como «Jornada de competició». Nombre por defecto: «Jornada del DD/MM/AAAA al DD/MM/AAAA». La plantilla del resumen pasa a llamarse `resum_jornada` con la variable `{{jornada}}`. Las plantillas por partido conservan el texto «Partit d’aquest cap de setmana» (son editables) | Petición del club |
| D-041 | 2026-10-16 | Aceptada | Todas las fechas visibles en formato **DD/MM/AAAA** (y DD/MM/AAAA HH:MM). Los campos de fecha son de texto con máscara `dd/mm/aaaa` en lugar de `<input type="date">`; la API sigue usando AAAA-MM-DD | Petición del club; el control nativo muestra MM/DD/AAAA en navegadores en inglés. Hay una prueba que impide reintroducir controles nativos de fecha u hora |
| D-042 | 2026-10-16 | Aceptada | Sin conflicto `MISSING_CALL_TIME` | Generaba avisos en todos los partidos propuestos; la convocatoria es opcional y la imagen solo la muestra si está informada |
| D-043 | 2026-10-16 | Aceptada | Tres o más F7 a la vez dan un solo conflicto `F7_CAPACITY_EXCEEDED` (sin repetir el solapamiento de cada pareja) | Mensaje más claro para el gestor |
| D-044 | 2026-10-16 | Aceptada | Los movimientos no crean versión; las versiones son puntos de restauración (propuesta, guardar, deshacer, confirmar). «Desfés els canvis» vuelve a la última versión guardada o, si no hay cambios, a la anterior | Historial útil sin una versión por cada arrastre |
| D-045 | 2026-10-16 | Aceptada | La vigencia de las reglas se evalúa con la fecha del primer día de la jornada | Una regla vale o no vale para toda la jornada |
| D-046 | 2026-10-16 | Aceptada | Cualquier cambio de un partido incrementa la `lock_version` de la programación de su jornada y, si estaba confirmada, la pasa a «pendent de revisar» | Concurrencia coherente entre la pantalla «Partits» y el calendario |
| D-047 | 2026-10-16 | Aceptada | Calendario: validación inmediata en el navegador con el validador TS; el servidor siempre valida y su respuesta es la verdad. Teclado y accesibilidad mediante «Mou a…» en lugar de un sensor de teclado sobre la cuadrícula | Un arrastre con teclado sobre una cuadrícula de 2 días × 2 campos × 56 cuartos no es práctico |
| D-048 | 2026-10-16 | Aceptada | Imágenes con iconos SVG en lugar de emojis y fuente Inter incrustada (copiada a `resources/fonts`, OFL) | Sin dependencia de fuentes del sistema ni de la red |
| D-049 | 2026-10-16 | Aceptada | `SendWhatsAppMessage` hace un solo intento; el estado `unknown` no se reintenta | Evitar mensajes duplicados en los grupos |
| D-050 | 2026-10-16 | Aceptada | Un borrador editado se conserva mientras no cambie la versión confirmada | No perder el trabajo del gestor al volver a previsualizar |
| D-051 | 2026-10-16 | Aceptada | Proveedor `log` (simulación) solo fuera de producción, salvo que `WHATSAPP_LOG_ENABLED=true` | Que no se pueda «enviar» por error a un simulador en producción |
| D-052 | 2026-10-16 | Aceptada | Límite `sensitive` (20/min por usuario) en propuesta, generación de imágenes y envío de avisos | Operaciones costosas o con efectos externos |
| D-053 | 2026-10-16 | Aceptada | Registros filtrados por un procesador de Monolog (`RedactingTap`) | Que no se filtren contraseñas, tokens ni teléfonos aunque una excepción los incluya |
| D-054 | 2026-10-16 | Aceptada | Proxies de confianza con un middleware que lee `config('gestpartits.trusted_proxies')` | Compatible con `config:cache` (`env()` no funciona fuera de `config`) |
| D-055 | 2026-10-16 | Aceptada | La copia de seguridad incluye el `.env` | Sin `APP_KEY` no se pueden descifrar los identificadores de WhatsApp |
