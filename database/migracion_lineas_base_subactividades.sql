-- Subactividades en las líneas base: foto de fechas, duración, avance e hito.
CREATE TABLE IF NOT EXISTS lineas_base_subactividades (
    linea_id INT NOT NULL,
    subactividad_id INT NOT NULL,
    actividad_id INT NOT NULL,
    codigo VARCHAR(60) NOT NULL DEFAULT '',
    nombre VARCHAR(200) NOT NULL DEFAULT '',
    partida VARCHAR(200) NOT NULL DEFAULT '',
    fecha_inicio DATE NOT NULL,
    fecha_fin DATE NOT NULL,
    duracion_dias INT NOT NULL DEFAULT 1,
    progreso TINYINT UNSIGNED NOT NULL DEFAULT 0,
    hito TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (linea_id, subactividad_id),
    KEY idx_lbsub_actividad (linea_id, actividad_id),
    FOREIGN KEY (linea_id) REFERENCES lineas_base(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Contador para la ficha y la comparación
SET @sql = (
  SELECT IF(
    (SELECT COUNT(*) FROM information_schema.columns
      WHERE table_schema = DATABASE() AND table_name = 'lineas_base' AND column_name = 'total_subactividades') = 0,
    'ALTER TABLE lineas_base ADD COLUMN total_subactividades INT NOT NULL DEFAULT 0',
    'DO 0'
  )
);
PREPARE stmt_lbsub FROM @sql;
EXECUTE stmt_lbsub;
DEALLOCATE PREPARE stmt_lbsub;
