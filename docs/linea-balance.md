# Línea de balance (takt) — `/balance`

Vista de planeación para obra repetitiva. Cada **frente** (piso, tramo, túnel, anillo) es una fila; cada **oficio** (partida) es una línea; la pendiente entre frentes es el **takt**.

- Tablas: `frentes` (proyecto, nombre, tipo, tipo_id, orden, color) y `actividades.frente_id`.
- **Tipos de frente**: catálogo `frentes_tipos` por proyecto (nombre, orden y color). Los base (Piso, Tramo, Túnel, Anillo, Otro) se siembran la primera vez; se pueden crear, editar y borrar. `LineaBalance::tipos/guardarTipo/editarTipo/eliminarTipo` y las rutas `POST /balance/tipo[/editar|/eliminar]`. Un tipo con frentes no se elimina.
- `LineaBalance::datos()` agrupa actividades por partida y frente, calcula el takt con días hábiles y detecta **cruces**: un oficio posterior que empieza en un frente antes de que termine el anterior.
- `LineaBalance::generar()` crea una actividad por frente para un oficio, con duración, takt y fecha de inicio; usa el `Calendario` del proyecto y recalcula la partida.
- UI: `/balance` con frentes, generador, diagrama SVG, alertas de cruce y huecos. No bloquea el programa; solo avisa.
- Migraciones `database/migracion_linea_balance.sql` y `database/migracion_frentes_tipos.sql`.
