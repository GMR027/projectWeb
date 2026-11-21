-- Incidentes de seguridad desde la app Android
-- 1) Personas involucradas (la web ya capturaba fecha, tipo, severidad, área,
--    actividad, descripción, causas, acciones, días y costo).
-- 2) Fotos del incidente (archivos en public/uploads/incidentes).
-- Es repetible: solo agrega la columna si falta.

SET @hay_col := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE() AND table_name = 'seguridad_incidentes' AND column_name = 'personas'
);
SET @sql_col := IF(@hay_col = 0,
  'ALTER TABLE seguridad_incidentes ADD COLUMN personas VARCHAR(1000) NOT NULL DEFAULT '''' AFTER descripcion',
  'SELECT 1');
PREPARE stmt_col FROM @sql_col;
EXECUTE stmt_col;
DEALLOCATE PREPARE stmt_col;

CREATE TABLE IF NOT EXISTS seguridad_incidente_fotos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    incidente_id INT NOT NULL,
    archivo VARCHAR(120) NOT NULL,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_sif_incidente (incidente_id),
    FOREIGN KEY (incidente_id) REFERENCES seguridad_incidentes(id) ON DELETE CASCADE
) ENGINE=InnoDB;
