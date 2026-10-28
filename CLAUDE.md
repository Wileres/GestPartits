# CLAUDE.md — GestPartits

Contexto para retomar el proyecto en una sesión nueva. Lo que no se pudo confirmar está marcado **POR CONFIRMAR**. No hay secretos en este archivo: los valores reales viven en el `.env` del servidor (no está en Git).

Idioma: la persona que mantiene el proyecto escribe en **español**; toda la **interfaz, mensajes, textos de las imágenes y comentarios del código están en catalán**. Responde en español y escribe el producto en catalán.

---

## 1. Descripción

Aplicación web para organizar los partidos de fútbol del **Joventut Sant Pere Màrtir** (Olot). Cada «jornada» (normalmente un sábado y un domingo) se introducen los partidos de cada equipo (a casa o a fuera), el programa propone horarios y campo para los partidos a casa, el gestor ajusta el calendario arrastrando tarjetas, lo confirma y de ahí salen: imágenes para Instagram, avisos de WhatsApp, el resumen de resultados y carteles para redes sociales.

Usuarios: personal del club. Dos roles: **Administrador** (todo, incluida configuración, usuarios, auditoría y correo) y **Gestor** (el día a día: equipos, temporadas, jornadas, partidos, programación, imágenes, avisos). El usuario inicial se llama `Gestor` pero tiene rol *admin* (no confundir nombre de usuario con rol; D-018).

## 2. Stack y arquitectura

- **Backend:** Laravel 13, PHP 8.5, API REST JSON, Sanctum (SPA con cookie + CSRF), MariaDB/MySQL, colas en base de datos, PHPMailer como transporte `phpmailer`.
- **Frontend:** React 19 + TypeScript, Vite 8, Tailwind CSS 4, shadcn/ui (Radix), dnd-kit, TanStack Query, react-hook-form + zod, React Router 8. Una SPA servida desde el mismo origen (`resources/frontend`).
- **Imágenes:** PNG generados en servidor con Chromium (chrome-php + vistas Blade) o GD; `FakeRenderer` en tests.

Estructura:
- `app/Domain/{Scheduling,Messaging,Imaging}` — PHP puro y determinista (sin Eloquent): planificador y detector de conflictos, plantillas de mensajes, diseño de imágenes (`LayoutPlanner`, `RowMetrics`, `PosterFormat`, `DayPoster`, `ResultsPoster`…).
- `app/Services` — lógica de aplicación (`MatchService`, `ImageService`, `NotificationService`, `Scheduling/ScheduleService`, `UserService`…).
- `app/Http` (Controllers/Api, Requests, Resources), `app/Policies`, `app/Models` (el modelo de `matches` es `GameMatch`), `app/Jobs` (`GenerateScheduleImages`, `GenerateResultsImages`, `SendWhatsAppMessage`), `app/Infrastructure/{Imaging,WhatsApp}` (renderizadores y proveedores), `app/Support` (`CallTime`, `TeamName`, `MailSettings`, `ClubSettings`, `ClubBranding`, `CatalanDate`…).
- `resources/frontend/src/{api,app,components,domain,features,hooks,i18n,lib}` — `features/*` por módulo (schedule, matches, teams, seasons, notifications, images, social, mail, appearance, help…). **Todos los textos** en `i18n/ca.ts`.
- `resources/views/images/*` — plantillas Blade de las imágenes (Chromium). `GdRenderer` replica el diseño con GD y debe mantenerse alineado.
- `lang/ca` — mensajes del backend. `tests/` (PHPUnit sobre MariaDB) y `resources/frontend/tests/` (Vitest). `docs/` — fases 1–8, `DECISIONS.md` (D-001…D-085, **léelo antes de cambiar comportamiento**) y `proves.md`. `deploy/` — plantillas nginx/apache/systemd/cron/backup.

Flujo de datos principal: temporada → jornada (`matchdays`) → partidos (`matches`) → programación (`schedules`, con `schedule_versions` = instantáneas JSON) → confirmación (`confirmed_version_id`) → imágenes (`generated_images`, ligadas a la versión confirmada) y avisos (`whatsapp_messages`).

## 3. Ejecutar y probar en local

Requisitos: PHP 8.5 (pdo_mysql, mbstring, intl, gd, xml, curl, zip, sodium, fileinfo), Composer 2, MariaDB 10.11+, Node 24, Chromium (o `IMAGE_RENDERER=gd`). Detalle completo en `README.md`.

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate      # y rellenar DB_* en .env
php artisan migrate
INITIAL_USER_PASSWORD='…' php artisan db:seed         # crea el usuario «Gestor» (admin)
./dev.sh            # servidor :8000 + Vite + cola (no ejecutar como root)
./dev.sh --test     # backend + frontend
```
Tests y calidad (todos deben quedar en verde antes de commitear):
```bash
php artisan test                    # 323 tests; usa la BD gestpartits_test en MariaDB (no SQLite)
npx vitest run                      # 116 tests; ejecutar SIEMPRE desde la raíz del repo
npx tsc --noEmit -p . && npx eslint resources/frontend/src
vendor/bin/pint --dirty             # estilo PHP
npm run build                       # genera public/build (ignorado por Git)
```
No ejecutar `prettier` sobre archivos existentes: no hay configuración y reformatea ficheros enteros.

## 4. Variables de entorno (solo nombres)

Aplicación: `APP_NAME`, `APP_ENV`, `APP_KEY` (cifra sesiones e identificadores de WhatsApp; **hay que respaldarla**), `APP_DEBUG`, `APP_URL`, `CLUB_TIMEZONE`, `APP_LOCALE`, `TRUSTED_PROXIES`, `LOG_*`.
Base de datos: `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`. Sesión/Sanctum: `SESSION_*`, `SANCTUM_STATEFUL_DOMAINS`. Cola/caché: `QUEUE_CONNECTION`, `CACHE_STORE`.
Correo (valores por defecto; el administrador puede sobrescribirlos desde la app, ver §7): `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_ENCRYPTION`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_TIMEOUT`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`.
Usuario inicial (seeder): `INITIAL_USER_USERNAME`, `INITIAL_USER_NAME`, `INITIAL_USER_EMAIL`, `INITIAL_USER_PASSWORD` (esta última se pasa solo en la línea de comandos, nunca en el `.env`).
WhatsApp: `WHATSAPP_DEFAULT_PROVIDER`, `WHATSAPP_LOG_ENABLED`, `WHATSAPP_GATEWAY_ENABLED/URL/TOKEN`, `WHATSAPP_META_ENABLED/PHONE_NUMBER_ID/TOKEN`, `WHATSAPP_TWILIO_ENABLED`, `TWILIO_ACCOUNT_SID/AUTH_TOKEN/WHATSAPP_FROM`.
Imágenes: `IMAGE_RENDERER` (`chromium`|`gd`|`fake`), `CHROMIUM_BINARY`, `GD_FONT_REGULAR`, `GD_FONT_BOLD`.
Los valores de producción están solo en el `.env` del servidor (y en la copia que hace `deploy/backup.sh`). Para regenerar `APP_KEY`: `php artisan key:generate` (invalida sesiones y los identificadores de WhatsApp cifrados).

## 5. Despliegue

Producción (confirmado en la conversación): servidor Debian con **ISPConfig**, Apache + PHP-FPM, sitio `gestpartits.ingeniuswil.com`, usuario del sitio `web17:client1`.
- Ruta real del sitio: `/var/www/clients/client1/web17` (`/var/www/gestpartits.ingeniuswil.com/web` es un enlace a `.../web17/web`).
- App: `/var/www/clients/client1/web17/private/gestpartits` (Git clonado aquí). PHP CLI: `/usr/bin/php8.5`. Composer: se usó `php8.5 /usr/local/bin/composer`, pero una vez dio «Could not open input file» → **POR CONFIRMAR** la ruta real (`which composer`).
- DocumentRoot apunta a `.../private/gestpartits/public` mediante *Directivas Apache* del sitio en ISPConfig, con `SetHandler "proxy:unix:/var/lib/php8.5-fpm/web17.sock|fcgi://localhost"` y `php_admin_flag engine off` dentro de `<IfModule php_module>`. **No desactivar `mod_php` globalmente**: otros sitios del servidor lo necesitan. ISPConfig puede regenerar el vhost; si la web vuelve a servirse con PHP 8.2 o da 500, revisar estas directivas.
- Cola: servicio systemd `gestpartits-queue` (User=web17, Group=client1, `/usr/bin/php8.5 artisan queue:work --sleep=3 --tries=1 --max-time=3600 --timeout=180`, `HOME=…/storage/app/chromium`). Scheduler: cron del sitio en ISPConfig, `* * * * *` `cd <app> && /usr/bin/php8.5 artisan schedule:run`.
- Chromium y `fonts-dejavu-core` se instalan con apt (si no, `IMAGE_RENDERER=gd`; el usuario preguntó si GD era equivalente: funciona pero es una réplica menos fiel).
- `public/build` **no está en Git**: se compila en el ordenador de desarrollo y se sube con rsync/SFTP. Nombre del host SSH del servidor: **POR CONFIRMAR** (en las instrucciones se usó `server.ingeniuswil.com` como ejemplo).

Actualizar a una versión nueva (como `web17`, desde `~/private/gestpartits`; en el ordenador de desarrollo primero `npm ci && npm run build` y `git push`):
```bash
git pull
php8.5 /usr/local/bin/composer install --no-dev --optimize-autoloader   # solo si cambió composer.lock
php8.5 artisan migrate --force                                           # solo si hay migraciones nuevas
php8.5 artisan config:cache && php8.5 artisan route:cache && php8.5 artisan view:cache
# desde el ordenador de desarrollo:
rsync -av --delete public/build/ web17@<servidor>:/var/www/clients/client1/web17/private/gestpartits/public/build/
# como root:
systemctl reload php8.5-fpm && systemctl restart gestpartits-queue
```
Reiniciar la cola es obligatorio cuando cambian clases o vistas de imágenes/avisos (el worker mantiene el código antiguo). `README.md` § «Instal·lació a producció» describe una instalación genérica con nginx; la de ISPConfig es la de arriba. Copias: `deploy/backup.sh` (adaptar rutas; incluye el `.env`).

## 6. Convenciones

- **Idioma:** UI y comentarios en catalán; mensajes de commit en catalán (así están en el historial). Fechas visibles **DD/MM/AAAA**; horas 24 h. Sin `<input type="date|time">` nativos (hay guardas en tests). Sin textos fijos en JSX: todo por `t('clave')`; hay tests que fallan con palabras en inglés/castellano en `ca.ts`.
- **Naming de dominio:** «Jornada» = `matchdays`; modelo `GameMatch` (tabla `matches`); enums como `VARCHAR` + backed enums PHP.
- **Backend:** FormRequests para validar, Policies/Gates (`manage-settings`, `view-audit`; `Gate::before` da todo al admin), Resources para respuestas, `Auditable`/`AuditLogger` para el registro (sin contraseñas ni tokens), transacciones + `lockForUpdate` + `lock_version` (409). Modo `Model::shouldBeStrict` fuera de producción. Estilo Pint.
- **Dominio puro** (`app/Domain`): sin dependencias de Laravel; se prueba con unit tests y fixtures JSON compartidas con el validador TypeScript (`tests/fixtures/scheduling`).
- **Frontend:** componentes shadcn en `components/ui` (tras `shadcn add`, quitar el paquete `cn`, ver README); `NativeSelect`, `FormField`, `ConfirmDialog` ya existen: reutilizarlos. Páginas con `React.lazy`. Mutaciones de la programación pasan por `useScheduleMutation` (versión optimista).
- **Tests:** todo cambio de comportamiento lleva test; Vitest desde la raíz; las pruebas del backend usan `SeedsClub`/`ConfirmsSchedule`.
- **Docs:** cada decisión nueva se añade como fila `D-NNN` en `docs/DECISIONS.md` (tabla) y se actualiza `README.md` y la ayuda (`ca.help` en `i18n/ca.ts`).

## 7. Decisiones de diseño (resumen; el detalle está en `docs/DECISIONS.md`)

Reglas de negocio fijadas por el club:
- Último kickoff de una franja a las **13:00**; kickoff manual entre **09:00 y 21:00** en cuadrícula de **15 min** (D-021, D-026). Sin preparación previa: ocupación = `[kickoff, kickoff + duración + margen posterior)` (D-022).
- Capacidad del campo: dos partidos de fútbol 7 a la vez, o uno de fútbol 11 (dos recursos H1/H2, D-004). Escoleta a partir de las **10:00**. Los partidos a casa sin hueco van al **sábado por la mañana** y después al **domingo por la mañana** (D-024). Sábado y domingo siempre en la misma pantalla (D-025).
- Estados de partido solo **Proposat / Confirmat / Cancel·lat**; los nuevos nacen *Confirmat* (D-056). Horario preferente por equipo (regla de ámbito equipo, D-058/059). Convocatoria por defecto = **1 h antes**, también fuera de casa, y sigue al partido mientras no se edite a mano (D-060/062). En partidos a fuera se escribe día y hora en el formulario rápido (D-063). La sugerencia casa/fuera es la contraria del último partido del equipo (D-064). Escudo único del club (D-061). Partidos a casa resaltados en azul.
- La jornada por defecto en Programació/Partits/Avisos es la **última creada** (D-068).
- Un movimiento manual bloquea el partido; regenerar respeta los bloqueos (D-010, D-057). Versiones = instantáneas de solo inserción; **restaurar versión** crea una versión nueva y, si la restaurada era una confirmación, vuelve a `confirmed` con sus imágenes y estados de avisos (D-080).
- Nombre del **rival** normalizado al guardar (`TeamName`): inicial en mayúscula en cada palabra (el resto no se toca), «de»/«del» en minúscula, «fue» en MAYÚSCULAS, letras sueltas en mayúscula (D-081). *Interpretación mía de «fue»: POR CONFIRMAR con el club (¿«fc»?).* Solo afecta al rival, no a los equipos del club.
- Resultados: `score_team`/`score_opponent`; guardar solo el resultado no invalida la programación confirmada (D-070).
- **Imágenes** (D-016, D-072…D-085): histórico por generación (`batch_id`), borrado manual, sin limpieza automática (no se borra si está adjunta a un aviso ya enviado). Formatos `story` 1080×1920 y `post` 1080×1350 (`PosterFormat`). Opciones: agrupar casa/fuera (títulos «Pic del bac» / «Visitant»), comprimir un día en una imagen con cajas estiradas (64–190 px), **toda la jornada en una sola imagen** (si no cabe a 64 px se hace una por día con aviso; ignora «agrupar»), formato story/post también en resultados. Si un día no cabe, se separa **casa y fuera** antes de paginar. Sin hora de convocatoria, sin nombre de la jornada, «vs» en todas las filas. Resultados: sin «casa/fuera»; si el partido es a fuera, el rival va primero y el marcador se invierte («Santa Eugenia 1 - 4 Prebenjamí S8»). Pie: `@olotjspm` izquierda, `#futboldebarri` derecha. Alternativa descartada: emojis en las imágenes (iconos SVG + fuente Inter incrustada, D-048).
- **Xarxes socials**: editor 100 % en el navegador (canvas), la foto no se sube. Dos tipos: *Resultat* (degradado negro abajo, marcador en azul) y *Dia de partit* (degradado negro arriba, cabecera editable «Dia de partit», partidos elegidos con equipos, hora y campo; D-085). *Interpretación mía: «Dia de partit» se puso aquí y no en el diálogo «Genera les imatges»: POR CONFIRMAR.*
- **WhatsApp** (D-014, D-027): vía A manual asistida por defecto (copiar/abrir enlace y marcar enviado); vía B pasarela no oficial con número dedicado, implementada pero desactivada; además resumen para un canal del club. Nada se envía sin confirmación explícita; `unknown` no se reintenta (D-015, D-049). Enlace manual con `api.whatsapp.com/send?text=` (D-067, **no verificado** si arregla los emojis «�»; existe botón de copiar como alternativa).
- **Correo:** PHPMailer; el administrador configura SMTP y remitente en Configuració → Correu (se guarda en `application_settings.mail`, contraseña cifrada con `Crypt` y nunca devuelta; si no hay nada guardado se usa el `.env`; hay correo de prueba) (D-083). Alternativa descartada: editar solo el `.env`.
- Pruebas contra MariaDB y no SQLite (mismos `CHECK`, `FOR UPDATE`, JSON; D-029). Fechas de partido en hora local (`DATE`+`TIME`), marcas de tiempo en UTC (D-008, D-032). Validador de conflictos duplicado en TS para respuesta inmediata en el drag and drop (D-003).

## 8. Problemas conocidos y trampas

- **Push a GitHub:** en el ordenador de desarrollo anterior `git push` fallaba (sin credenciales); el dueño hacía el push con un *personal access token*. No intentar guardar credenciales en el repo. El estado real de `origin/main` al terminar la sesión: ver sección 9.
- `docs/DECISIONS.md` mezcla castellano y catalán, y D-074 (resultados 1080×1920) quedó **superada por D-076/D-077** (resultados como publicación 1080×1350 por defecto, ahora con elección de formato). D-034 («Gestor y admin…») es anterior a la separación real de permisos: lo que manda es el código (`UserPolicy`, gates `manage-settings`/`view-audit`; el gestor no ve Configuració, Usuaris ni Auditoria).
- **Horas:** MariaDB devuelve `HH:MM:SS`, la UI usa `HH:MM`. Comparar siempre normalizado (`Time::hm`); un bug real consistió en bloquear a mano cualquier partido editado por comparar `11:15` con `11:15:00` (D-071).
- Un fichero `public/hot` obsoleto hace que Laravel busque Vite en dev y no sirva `public/build` (pantalla en blanco). Borrarlo si no hay Vite en marcha.
- Tras desplegar, sin `systemctl restart gestpartits-queue` las imágenes siguen con el código viejo. Sin `rsync` de `public/build`, el frontend queda desfasado respecto al backend.
- El editor social usa `ctx.filter` para brillo/contraste/saturación (Safari antiguo puede ignorarlo). En jsdom no hay canvas: los tests lo simulan.
- Chromium no puede ejecutarse como root ni sin `HOME` escribible (`storage/app/chromium`).
- No se ha verificado visualmente `GdRenderer` con las últimas opciones (se probó con Chromium); las pruebas solo comprueban dimensiones.
- `Fecha de la jornada ≠ id`: la «última jornada» es el `id` más alto de la temporada.
- Restaurar versión: los avisos entregados de otra versión pasan a «Desactualitzat» y los de la versión restaurada vuelven a «Enviat»; solo se restaura el último por partido y destino (D-080).
- La normalización de nombres no toca el resto de la palabra: «CF OLOT» sigue en mayúsculas.

## 9. Estado actual (2026-10-28)

Terminado y probado (323 tests backend + 116 frontend, Pint/tsc/eslint/build limpios):
- Gestión de equipos, categorías, temporadas, jornadas, partidos (formulario rápido, resultados), reglas/franjas/bloqueos, proposta automàtica, calendario con arrastrar y soltar, versiones (guardar, deshacer, comparar, **restaurar**), confirmación.
- Imágenes: formatos story/post, comprimir, agrupar casa/fuera, paginación por casa/fuera, **toda la jornada en una imagen**, sin convocatoria, histórico con descarga y borrado, imagen de resultados (story/post).
- Xarxes socials: *Resultat* y *Dia de partit*.
- Avisos de WhatsApp (vía manual), plantillas y destinos, auditoría, usuarios, ayuda (Guia ràpida y completa), configuración de correo por el administrador, aparença.
- Despliegue en ISPConfig funcionando (PHP 8.5, FPM, cola, cron).

Último commit local: `b79b789` («Imatges sense hora de convocatòria, tota la jornada en una sola imatge i «Dia de partit» a xarxes socials»). Las dos últimas tandas (restaurar versiones/correo y las de imágenes/Dia de partit) **pueden no estar desplegadas** en el servidor; confirmar con el dueño qué versión corre.

A medias / sin hacer:
- **Alta de usuario no envía correo** (nunca se implementó; solo existe el correo de «He oblidat la contrasenya»). Se propusieron dos opciones (correo con enlace para crear contraseña — recomendada — o con contraseña inicial) y **no hubo respuesta**.
- El enlace de WhatsApp con `api.whatsapp.com` no está verificado.

## 10. Próximos pasos (priorizados)

1. Confirmar con el dueño qué tandas están desplegadas y, si no, desplegarlas (§5) — incluye la migración `2026_10_27_000100_normalize_opponent_names` y las de resultados/histórico de imágenes.
2. Decidir y, si procede, implementar el correo de bienvenida al crear usuarios (opción 1: enlace de restablecimiento).
3. Verificar visualmente en producción: etiquetas «Pic del bac»/«Visitant» (ancho de la etiqueta de fila), jornada completa en una imagen, resultados a fuera con orden invertido, «Dia de partit».
4. Confirmar la regla de «fue» (¿«fc»?) y la ubicación de «Dia de partit».
5. Verificar que los emojis de WhatsApp llegan bien con `api.whatsapp.com`; si no, revisar la codificación del enlace.
6. Probar `GdRenderer` con las opciones nuevas si se piensa usar sin Chromium.
7. Limpiar `docs/DECISIONS.md` (marcar D-074 como sustituida).

## 11. Reglas de trabajo para futuras sesiones

- **No refactorizar ni cambiar comportamiento sin que se pida.** Ante ambigüedad, preguntar o dejar la interpretación explícita en la respuesta (y en este archivo como POR CONFIRMAR).
- **Nunca inventar ni escribir valores de secretos** (contraseñas, tokens, `APP_KEY`, credenciales SMTP/WhatsApp). Usar solo nombres de variables y apuntar al `.env` del servidor.
- Tras cada cambio: tests + Pint + tsc + eslint + build en verde; **commit y `git push`**; añadir la decisión a `docs/DECISIONS.md`, actualizar README y la ayuda de la app si cambia el comportamiento.
- **Al terminar cada tanda, dar al dueño los comandos exactos para actualizar el servidor** (§5), indicando si hay migraciones y si hay que subir `public/build` y reiniciar la cola.
- Si `git push` falla por credenciales, decirlo claramente y no intentar eludirlo; el dueño sube con su token.
- Los mensajes de commit terminan con la línea de atribución indicada por el entorno (`Co-Authored-By: Claude …`).
- Responder en español, producto en catalán. No duplicar código del validador de conflictos sin actualizar las fixtures compartidas.
