# GestPartits — Fase 5: calendario visual

> Estado: terminada. Fecha: 2026-10-16.

## Pantalla «Programació de la jornada» (`/`)

- **Selectores:** temporada y jornada (guardados en la URL: `?temporada=…&jornada=…`).
- **Acciones:** Genera una proposta · Desfés els canvis · Desa la programació · Versions · Confirma la programació · Genera les imatges · Envia els avisos (estas dos, solo con la programación confirmada).
- **Calendario:** sábado y domingo uno al lado del otro (en el móvil, uno debajo del otro, sin pestañas). Eje de 09:00 a 23:00, cuadrícula de 15 minutos y columnas Camp 1 | Camp 2. Las tarjetas F11 ocupan las dos columnas.
  - Las franjas habituales salen sombreadas y los bloqueos, rayados en rojo, con el motivo.
- **Tarjetas:** equipo, rival, hora de inicio y de final, conflicto (icono y color) y botones de bloquear y «Mou a…».
  - La parte rayada de la tarjeta es el margen posterior, de modo que se ven a la vez la duración del partido y la ocupación del campo.
- **Colores:** rojo = conflicto crítico, ámbar = aviso, verde = válido, anillo azul = bloqueado o confirmado. Siempre van con icono y texto, y hay una leyenda.
- **Paneles:** «Partits sense programar» (también es un destino para sacar un partido del calendario, con el motivo de la última propuesta), «Partits a fora» por día y «Conflictes i avisos» con los mensajes del servidor.

## Arrastrar y soltar

- **Validación en el navegador:** dnd-kit con ratón y pantalla táctil (pulsación de 250 ms). Mientras se arrastra, el validador TypeScript (`domain/scheduling/detector.ts`, el mismo que pasa las fixtures del PHP) pinta el destino de verde, ámbar o rojo, y se anuncia en catalán a los lectores de pantalla.
- **Validación en el servidor:** al soltar, se llama a `POST /moves` y el servidor vuelve a validar el movimiento. La pantalla siempre muestra lo que devuelve el servidor (estado y conflictos). Si la versión ha cambiado (`409`), se recarga.
- **F11:** soltar un F11 en cualquier columna lo coloca en el Camp complet.
- **Teclado y accesibilidad:** cada tarjeta tiene «Mou a…» (día, hora en cuartos y campo), con la validación al momento y la opción «Treu del calendari».
- **Edición:** hacer clic en la tarjeta abre el panel lateral del partido (el mismo de la pantalla «Partits»).

## Diálogos

- **Proposta generada:** número de partidos colocados, no programados con el motivo y la tabla «Abans → Ara» con todos los cambios. Es la comparación con la situación anterior.
- **Versions:** lista de versiones con quién y cuándo. Se eligen dos para ver las diferencias.
- **Confirmar:** si hay conflictos críticos, los lista y no deja confirmar.

## Pruebas

Frontend: 60, entre ellas las del calendario (renderizado de los dos días, la bandeja, los partidos fuera y los conflictos, «Mou a…» con un bloqueo detectado al momento, F11 solo en el Camp complet, confirmación bloqueada, propuesta con cambios y recarga si llega un `409`).

## Revisión en navegador

He revisado el calendario con Chromium headless (1366 px y 390 px), con una propuesta real sobre una jornada completa y con un arrastre real con ratón (CDP). La vista previa sale en rojo sobre otro partido y, después de soltar, el partido queda bloqueado y en conflicto. La revisión sirvió para corregir la etiqueta de la primera hora, que quedaba cortada.
