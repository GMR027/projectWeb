-- Subactividades de una actividad del programa de obra.
-- Solo se gestionan desde la edición de la actividad y alimentan su avance
-- (promedio de los % de las subactividades). No aparecen en el programa,
-- dependencias, línea de tiempo ni lookahead.
CREATE TABLE IF NOT EXISTS subactividades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    actividad_id INT NOT NULL,
    nombre VARCHAR(200) NOT NULL,
    fecha_inicio DATE NOT NULL,
    fecha_fin DATE NOT NULL,
    status TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'avance de la subactividad, 0-100',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE CASCADE,
    KEY idx_subactividades_actividad (actividad_id)
) ENGINE=InnoDB;
