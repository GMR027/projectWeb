# Línea de balance (takt) — `/balance`

Vista de planeación para obra repetitiva. Cada **frente** (piso, tramo, túnel, anillo) es una fila; cada **oficio** (partida) es una línea; la pendiente entre frentes es el **takt**.

- Tablas: `frentes` (proyecto, nombre, tipo, orden, color) y `actividades.frente_id`.
- `LineaBalance::datos()` agrupa actividades por partida y frente, calcula el takt con días hábiles y detecta **cruces**: un oficio posterior que empieza en un frente antes de que termine el anterior.
- `LineaBalance::generar()` crea una actividad por frente para un oficio, con duración, takt y fecha de inicio; usa el `Calendario` del proyecto y recalcula la partida.
- UI: `/balance` con frentes, generador, diagrama SVG, alertas de cruce y huecos. No bloquea el programa; solo avisa.
- Migración `database/migracion_linea_balance.sql`.
