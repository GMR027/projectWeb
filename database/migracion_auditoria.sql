-- Historial de cambios / auditoría global de las herramientas de IA. Solo se agregan filas: revertir o reabrir crea otro evento.
CREATE TABLE IF NOT EXISTS auditoria_cambios (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    herramienta VARCHAR(30) NOT NULL,                 -- programador | inconsistencias | materiales | ...
    evento ENUM('aplicado','revertido','rechazado','reabierto') NOT NULL,
    accion VARCHAR(40) NOT NULL,
    entidad_tipo VARCHAR(20) NOT NULL DEFAULT '',     -- actividad | partida | material | restriccion | dependencia | submittal | proyecto
    entidad_id INT NULL,
    entidad_nombre VARCHAR(255) NOT NULL DEFAULT '',
    resumen VARCHAR(500) NOT NULL DEFAULT '',
    antes_json MEDIUMTEXT NULL,
    despues_json MEDIUMTEXT NULL,
    referencia_id INT NULL,                           -- hallazgo, propuesta o corrida que originó el cambio
    modelo VARCHAR(150) NOT NULL DEFAULT '',
    usuario VARCHAR(150) NOT NULL DEFAULT '',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_proyecto_fecha (proyecto_id, creado),
    KEY idx_entidad (proyecto_id, entidad_tipo, entidad_id),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;
