# GestPartits — Fase 4: planificador

> Estado: terminada. Fecha: 2026-10-16.

## Componentes

| Pieza | Ubicación | Qué hace |
|---|---|---|
| Modelo del dominio | `app/Domain/Scheduling/Model` | `ScheduleInput` (días, cuadrícula, horario operativo, franjas efectivas, bloqueos por día, reglas y partidos), `MatchItem`, `SlotWindow`, `BlockInterval`, `RuleSpec`, `Placement` y `FieldResources`. PHP puro, sin Laravel. Se construye desde la base de datos o desde JSON |
| Reglas | `Rules/RuleResolver` | Una sola preferencia por partido (equipo > categoría > modalidad, después prioridad y después id) y todas las ventanas vigentes |
| Conflictos | `Conflicts/ConflictDetector` | 17 códigos con su severidad (crítico o aviso), en orden determinista |
| Propuesta | `Proposal/ProposalEngine` | Algoritmo voraz determinista con MRV y reparación limitada. Devuelve los partidos colocados, los no programados con el motivo, los recolocados y los conflictos finales |
| Datos de la BD | `app/Services/Scheduling/ScheduleInputFactory` | Franjas efectivas (las excepciones por fecha sustituyen a las semanales), bloqueos recortados por día, reglas activas e intervalo mínimo de la categoría |
| Casos de uso | `app/Services/Scheduling/ScheduleService` | Proponer, validar o hacer un movimiento, bloquear, guardar, deshacer, confirmar y comparar versiones. Usa transacciones, `SELECT … FOR UPDATE` y `lock_version` |
| Textos | `lang/ca/conflicts.php`, `lang/ca/schedule.php` y `ConflictPresenter` | Mensajes en catalán con los nombres de los equipos y la regla |
| Validador TS | `resources/frontend/src/domain/scheduling` | Réplica del detector para el calendario (Fase 5) |
| Fixtures compartidas | `tests/fixtures/scheduling/*.json` | 18 casos que pasan exactamente igual en PHPUnit y en Vitest |

## Modelo del campo

El campo son dos recursos (`camp_1` y `camp_2`). Un F7 ocupa uno y un F11 ocupa los dos. La ocupación es `[hora, hora + duración + margen)`, más el intervalo mínimo de la categoría frente a otros partidos de la misma área. Así, las cuatro reglas de capacidad (2×F7 a la vez, no hay un tercer F7, F11 excluye F7, F11 excluye F11) se reducen a una sola: ningún recurso puede tener dos ocupaciones solapadas. Para dar un mensaje claro, se informa de un único conflicto `F7_CAPACITY_EXCEEDED` cuando coinciden tres o más F7, en lugar de repetir el solapamiento de cada pareja.

## Algoritmo de propuesta

1. **Qué se mueve:** solo los partidos activos en casa que no están bloqueados ni confirmados. Los fijos y los bloqueos ocupan el campo de entrada.
2. **Orden:** reglas obligatorias, después recomendadas (por prioridad) y después partidos sin regla. Dentro de cada grupo: menos candidatos primero, después orden de categoría, orden de equipo e id.
3. **Candidatos:** cuartos de hora dentro de una franja y del horario operativo, que cumplan las ventanas obligatorias y con el área libre. Un F7 solo se propone en Camp 1 o Camp 2; un F11 siempre en el Camp complet.
4. **Puntuación:**
   - Con preferencia: 10.000 si es otro día, más los minutos de distancia, más 5 si el área no es la preferida.
   - Sin preferencia: solo las franjas con orden de relleno (sábado mañana y después domingo mañana), lo más temprano posible.
   - Las ventanas recomendadas que no se cumplen suman 5.000 más la distancia.
5. **Reparación:** si un partido no cabe, se prueba a mover uno solo de los que ya se han colocado (que no tenga regla obligatoria).
6. **Sin hueco:** el partido queda en «sense programar» con el motivo `no_slot` (ninguna franja lo admite) o `field_full` (todo está ocupado).

## API

```
GET  /api/matchdays/{matchday}/schedule
POST /api/schedules/{id}/proposal | save | undo | confirm      {version}
POST /api/schedules/{id}/moves/validate                        {match_id, match_date, kickoff_time, field_area_id}
POST /api/schedules/{id}/moves                                 {…, version}   (sin hora = sense programar)
POST|DELETE /api/schedules/{id}/matches/{match}/lock           {version}
GET  /api/schedules/{id}/versions/{a}/compare/{b}
```

- **Versiones:** cada propuesta guarda una versión `pre_proposal` y otra `proposal`. «Desa la programació» crea una versión `manual_save` y la confirmación, una versión `confirmation`.
- **Deshacer:** «Desfés els canvis» vuelve a la última versión guardada si hay cambios sin guardar; si no los hay, vuelve a la versión anterior.
- **Movimientos:** un movimiento no crea versión. Bloquea el partido y, si la programación estaba confirmada, la pasa a «Programació pendent de revisar».
- **Confirmar:** con conflictos críticos responde `423` con la lista. Si no los hay, confirma los partidos activos y emite el evento `ScheduleConfirmed`. Es idempotente.
- **Concurrencia:** cualquier cambio de partido desde la pantalla «Partits» incrementa la versión de la programación. El calendario de otro gestor recibe `409` y se recarga.

## Pruebas

- **Backend: 211** (70 nuevas). Dominio: fixtures compartidas, propuesta con las reglas del club (casos 8–11), bloqueados respetados (caso 20), confirmados, bloqueos (caso 12), partidos fuera (caso 13), motivos de los no programados, regla obligatoria inalcanzable, reparación, determinismo e idempotencia. API: confirmación con conflictos (caso 14), guardar sin enviar nada (caso 15), concurrencia (`409`), movimientos imposibles rechazados por el backend, deshacer, comparar y «pendent de revisar».
- **Frontend: 55**, entre ellas las 18 fixtures compartidas y la previsualización de movimientos.
