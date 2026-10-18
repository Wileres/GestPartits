# GestPartits — Fase 3: gestión funcional

> Estado: **pendiente de revisión**. Fecha: 2026-10-16.

> **Nota (2026-10-16, D-040 y D-041):** en la interfaz, la unidad de planificación que este documento llama «fin de semana» (`matchdays`) se llama **«Jornada»**, y todas las fechas se muestran en formato **DD/MM/AAAA**.

> **Nota (2026-10-18, D-056 a D-061):** els partits només tenen els estats **Proposat, Confirmat i Cancel·lat** (un partit nou és *Confirmat*; «Pendent», «Ajornat» i «Descansa» ja no existeixen: un equip sense partit simplement no en té). L'**escut** és únic per a tot el club (Configuració → Aparença) i ja no es puja per equip. L'**horari preferent** de cada equip s'edita des de la seva fitxa i es carrega en crear un partit a casa, amb la convocatòria una hora abans. Els partits **ja no es protegeixen pel seu estat**: només el bloqueig impedeix que la proposta automàtica els mogui.

## Contenido

| Área | Backend (API) | Pantalla | Quién |
|---|---|---|---|
| Categorías | `GET/POST/PUT/DELETE /api/categories`, `POST /api/categories/durations-reviewed` | **Configuració → Categories**: modalidad, duración, margen posterior, intervalo mínimo y orden. Aviso «Durades de demostració» hasta que se revisan | Admin (los demás solo consultan) |
| Equipos | `GET/POST/PUT/DELETE /api/teams`, `PUT /api/teams/order`, `GET/POST/DELETE /api/teams/{id}/logo` | **Equips**: lista ordenable arrastrando (dnd-kit, también con el teclado), color, modalidad y duraciones propias o heredadas, escudo y horario preferente | Gestor y admin |
| Temporadas y fines de semana | `/api/seasons`, `/api/seasons/{id}/matchdays`, `/api/matchdays/{id}` | **Temporades**: una sola temporada actual, fines de semana de hasta 4 días dentro de la temporada y nombre automático en catalán | Gestor y admin |
| Partidos | `/api/matchdays/{id}/matches`, `…/matches/bulk`, `…/home-away-suggestions`, `/api/matches/{id}` | **Partits**: hoja rápida con la sugerencia de casa o fuera y un panel lateral de edición (en casa: campo, bloqueo, excepción; fuera: instalación, dirección, municipio, Maps) | Gestor y admin |
| Franjas | `/api/availability-slots` | **Configuració → Franges horàries**: semanales y excepciones por fecha, primera y última hora de inicio, orden de relleno | Admin |
| Bloqueos | `/api/field-blocks` | **Configuració → Bloquejos del camp**: todo el campo o Camp 1 / Camp 2 | Admin |
| Reglas | `/api/scheduling-rules` | **Configuració → Regles horàries**: ámbito (equipo, categoría o modalidad), preferencia (día, hora, campo), ventana (no antes de / no después de), obligatoria o recomendada, prioridad y vigencia | Admin |
| Datos de referencia | `GET /api/reference-data` | Áreas del campo, horario operativo, intervalo, aviso de duraciones | Todos |

## Reglas que se aplican al guardar

- **Partidos en casa:** la hora es siempre un cuarto de hora entre las 09:00 y las 21:00 (inclusive). La interfaz solo ofrece esas horas y el servidor vuelve a comprobarlo. Una hora fuera de las franjas está permitida; el aviso ámbar llegará con el motor de la Fase 4.
- **Colocación completa:** para colocar un partido en casa hacen falta la fecha, la hora y el campo. Un F11 solo puede usar el **Camp complet** (un F11 sin campo se asigna automáticamente al campo completo).
- **Bloqueo automático:** una colocación manual bloquea el partido (`is_locked`) para que la propuesta no lo mueva, salvo que el gestor desmarque la casilla.
- **Partidos fuera:** no ocupan el campo local. La hora es libre (por ejemplo, 10:10) y la convocatoria no puede ser posterior al partido.
- **Descansa:** vacía el rival, las horas y la instalación, y el partido no aparece en la imagen.
- **Concurrencia:** cada partido lleva `lock_version`. Si dos gestores lo editan a la vez, el segundo recibe un `409` con el mensaje «Algú altre ha modificat aquestes dades…» y la pantalla recarga los datos.
- **Hoja rápida:** se guarda todo o nada y no duplica equipos que ya tienen partido. Para registrar dos partidos del mismo equipo (por ejemplo, una recuperación) se usa «Afegeix un partit»; el conflicto se avisará en la Fase 4.
- **Sugerencia de casa o fuera:** la contraria del último partido jugado. Ignora descansos, aplazados y cancelados, y no presupone una alternancia estricta.
- **Escudos:** se aceptan PNG, JPEG o WebP de hasta 2 MB y 2000 px. El tipo se comprueba por el contenido. La imagen se recodifica a PNG con nombre aleatorio (ULID) en el disco privado y solo la sirve a usuarios autenticados la ruta `/api/teams/{id}/logo`. SVG, GIF y PHP disfrazado de PNG se rechazan.
- **Auditoría:** todas las altas, cambios y bajas de categorías, equipos, temporadas, fines de semana, partidos, franjas, bloqueos y reglas quedan en `audit_logs` (trait `Auditable`).

## Pruebas

- Backend: **141** (83 nuevas). Entre ellas el caso 17 (`ConfigurationAuthorizationTest`: un gestor no puede cambiar ninguna configuración, 13 endpoints) y el caso 19 (`TeamLogoUploadTest`: SVG, GIF, PHP disfrazado y extensión `.php` rechazados; recodificación y nombre aleatorio). También concurrencia (`409`), hoja rápida (todo o nada), sugerencia de casa o fuera, cuadrícula y horario operativo, y F11 solo en el campo completo.
- Frontend: **30** (13 nuevas): hoja rápida con la sugerencia y el envío, panel de partido fuera, horas solo en cuartos de 09:00 a 21:00, convocatoria posterior bloqueada, menú de configuración por rol, orden de equipos, utilidades de fechas, horas y reglas, y una salvaguarda contra el paquete `cn`.

```bash
php artisan test && npm test && npm run typecheck && npm run lint && vendor/bin/pint --test
```

## Verificación manual

1. `npm run build` y `php artisan serve`. Entra como administrador.
2. **Configuració → Categories**: revisa las duraciones y pulsa «Ja les he revisat».
3. **Equips**: arrastra un equipo o, con el teclado, haz Tab hasta el asa, pulsa la barra espaciadora y usa las flechas. Recarga la página: el orden se mantiene. Edita un equipo y sube un escudo PNG. Prueba a subir un `.svg`: se rechaza.
4. **Temporades → Nou cap de setmana**: elige un sábado; el domingo se rellena solo.
5. **Partits**: elige el fin de semana, marca Casa, Fora o Descansa para algunos equipos (fíjate en «Suggeriment: …» cuando hay un fin de semana anterior) y pulsa «Crea els partits (n)».
6. Abre un partido en casa: la hora solo ofrece cuartos de hora entre las 09:00 y las 21:00, y un F11 solo puede elegir el Camp complet. Abre un partido fuera: aparecen la instalación, el municipio y Maps.
7. Abre el mismo partido en dos pestañas, guarda en una y después en la otra: aparece el aviso de conflicto.
8. Entra como gestor: el menú no muestra «Configuració» y `/configuracio/regles` muestra «No tens permís…».

## Notas e incidencias

- **shadcn:** al añadir componentes, el CLI vuelve a instalar el paquete de npm `cn`. Lo he desinstalado y una prueba de Vitest falla si reaparece. Después de cada `shadcn add` hay que ejecutar `npm uninstall cn` y revisar los imports.
- **Horas:** el campo de hora nativo muestra AM/PM en navegadores en inglés. Por eso la aplicación usa desplegables de cuartos de hora para las horas de partidos en casa, franjas, reglas y bloqueos, y un campo de texto `hh:mm` para las horas libres (partidos fuera y convocatorias).
- **Fechas:** desde D-041 son campos de texto con máscara `dd/mm/aaaa` y todas las fechas se muestran en formato DD/MM/AAAA, sea cual sea el idioma del navegador.
- **Accesibilidad del arrastre:** dnd-kit anuncia los movimientos en inglés por defecto. Se han sustituido por anuncios en catalán (`lib/dnd.ts`).
- **Revisión en navegador:** he revisado las pantallas con Chromium headless a 1366 px y 390 px. Con esa revisión se han corregido las horas en AM/PM, las fechas cortadas en el desplegable y la tabla de la hoja rápida en el móvil (ahora son tarjetas).
- **Paquete JS:** las páginas se cargan bajo demanda. El paquete inicial pasa de 680 kB a 334 kB (105 kB comprimido).
