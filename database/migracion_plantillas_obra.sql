-- Tipo de obra de cada proyecto (plantilla): da contexto a la IA, reglas de secuencia y una base de partidas/actividades.
CREATE TABLE IF NOT EXISTS proyecto_plantilla (
    proyecto_id INT PRIMARY KEY,
    plantilla VARCHAR(30) NOT NULL,
    base_aplicada TINYINT(1) NOT NULL DEFAULT 0,
    actualizado TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;
