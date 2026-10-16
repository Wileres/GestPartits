# GestPartits — Fase 7: seguridad y producción

> Estado: terminada. Fecha: 2026-10-16.

## Medidas de seguridad implementadas

| Requisito | Implementación | Prueba |
|---|---|---|
| Hash de las contraseñas | Argon2id (`HASH_DRIVER`) y cambio obligatorio de la contraseña inicial | `PasswordStorageTest`, `PasswordChangeTest` |
| Intentos repetidos | 5 por usuario e IP (bloqueo de 60 s) y 20 por IP y minuto; los fallos se auditan | `LoginTest` |
| Fijación de sesión | Sesión regenerada al iniciar sesión e invalidada al cerrarla | `HardeningTest` |
| CSRF | Sanctum stateful y XSRF-TOKEN | `CsrfProtectionTest` |
| XSS | React escapa el contenido; `dangerouslySetInnerHTML` está prohibido por ESLint; las plantillas no evalúan código | `TemplateRendererTest` |
| CSP y cabeceras | CSP con nonce (scripts), `nosniff`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, COOP/CORP y HSTS en producción con HTTPS | `SecurityHeadersTest`, `HardeningTest` |
| Cookies | `HttpOnly`, `Secure` (producción), `SameSite=Lax` y sesión cifrada | `SecurityHeadersTest` |
| Autorización | Policies y gates; el administrador por `Gate::before`; todas las rutas exigen sesión y las de configuración, ser administrador | `RouteProtectionTest`, `ConfigurationAuthorizationTest` |
| Límite de peticiones | API 180/min, inicio de sesión, recuperación de contraseña y `sensitive` (propuesta, imágenes, envíos) 20/min | `HardeningTest`, `LoginTest` |
| Archivos subidos | MIME por contenido, extensión, tamaño y dimensiones; recodificación a PNG, nombre ULID, disco privado y Nginx/Apache que solo ejecutan `index.php` | `TeamLogoUploadTest` |
| Secretos | `.env` fuera de Git; identificadores de WhatsApp cifrados (`APP_KEY`); la API solo devuelve los últimos 4 caracteres | `SecurityHeadersTest`, `WhatsAppNotificationTest` |
| Registros sin datos sensibles | `RedactSensitiveData` en los canales `single` y `daily` (contraseñas, tokens, Bearer y teléfonos) | `HardeningTest` |
| Auditoría | `audit_logs` de solo inserción (el modelo impide modificar y borrar); accesos, cambios de horario, propuestas, confirmaciones, envíos y configuración; pantalla **Auditoria** con filtros | `SchemaConstraintsTest`, `HardeningTest` |
| Transacciones y duplicados | `SELECT … FOR UPDATE`, confirmación idempotente, clave de idempotencia y transición atómica de los envíos | `ScheduleApiTest`, `WhatsAppNotificationTest` |
| Concurrencia | `lock_version` en las programaciones y los partidos (`409`) | `ScheduleApiTest`, `MatchApiTest` |
| Proxies | `TRUSTED_PROXIES` (middleware que lee la configuración, compatible con `config:cache`) | `HardeningTest` |
| Copias de seguridad | `deploy/backup.sh` (base de datos, archivos y `.env`; 30 días) y cron diario | — |

## Producción

- **Configuración del servidor:** `deploy/nginx`, `deploy/apache`, `deploy/systemd` (worker de la cola), `deploy/cron` (scheduler y copia) y `deploy/php`.
- **Tareas programadas:** `routes/console.php` (limpieza de imágenes antiguas, enlaces de contraseña y trabajos fallidos).
- **Cachés de producción:** `config:cache`, `route:cache` y `view:cache` están verificadas.
- **Instalación, actualización, copias de seguridad, WhatsApp e imágenes:** paso a paso en `README.md`.

## Pruebas

- **Backend: 251**, de las cuales 17 son nuevas en esta fase: protección de todas las rutas, endurecimiento y palabras castellanas.
- **Frontend: 64.**
- Correspondencia con los 22 casos del enunciado: `docs/proves.md`.

## Riesgos y límites conocidos

- **WhatsApp:** sin la pasarela (vía B), la publicación en grupos es manual asistida. La vía B tiene riesgo de bloqueo del número.
- **Imágenes con Chromium:** necesitan unos 300 MB de RAM durante unos segundos por imagen. Se generan en la cola, una tarea cada vez.
- **Resumen del canal:** solo agrupa los partidos de sábado y domingo. Los de otros días (festivos) aparecen en los grupos de los equipos y en las imágenes.
