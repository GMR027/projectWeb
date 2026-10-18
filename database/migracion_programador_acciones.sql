-- Programador de actividades: seguimiento de obstáculos/mejoras, partidas aceptadas y respaldos para deshacer la aceptación.
ALTER TABLE programador_propuestas ADD COLUMN seguimiento_json LONGTEXT NULL AFTER nombre;
ALTER TABLE programador_propuestas ADD COLUMN aceptadas_json LONGTEXT NULL AFTER seguimiento_json;

-- Estado de las actividades y de sus predecesoras justo antes de aceptar una partida (no depende de que la propuesta siga existiendo)
CREATE TABLE IF NOT EXISTS programador_respaldos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    propuesta_id INT NOT NULL,
    partida_id INT NOT NULL,
    datos_json LONGTEXT NOT NULL,
    deshecho TINYINT(1) NOT NULL DEFAULT 0,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;
