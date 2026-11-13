-- Restricciones propias de una subactividad.
-- La restricción sigue perteneciendo a la actividad (actividad_id = padre) y, si es
-- de una hija, lleva subactividad_id. Las restricciones de la actividad quedan con
-- subactividad_id NULL.
-- Idempotente: se puede ejecutar aunque la columna o la FK ya existan.
SET @sql = (
  SELECT IF(
    (SELECT COUNT(*) FROM information_schema.columns
      WHERE table_schema = DATABASE() AND table_name = 'restricciones' AND column_name = 'subactividad_id') = 0,
    'ALTER TABLE restricciones ADD COLUMN subactividad_id INT NULL AFTER actividad_id',
    'DO 0'
  )
);
PREPARE stmt_restr_sub FROM @sql;
EXECUTE stmt_restr_sub;
DEALLOCATE PREPARE stmt_restr_sub;

SET @sql = (
  SELECT IF(
    (SELECT COUNT(*) FROM information_schema.statistics
      WHERE table_schema = DATABASE() AND table_name = 'restricciones' AND index_name = 'idx_restriccion_subactividad') = 0,
    'ALTER TABLE restricciones ADD KEY idx_restriccion_subactividad (subactividad_id)',
    'DO 0'
  )
);
PREPARE stmt_restr_sub_idx FROM @sql;
EXECUTE stmt_restr_sub_idx;
DEALLOCATE PREPARE stmt_restr_sub_idx;

SET @sql = (
  SELECT IF(
    (SELECT COUNT(*) FROM information_schema.table_constraints
      WHERE table_schema = DATABASE() AND table_name = 'restricciones' AND constraint_name = 'fk_restriccion_subactividad') = 0,
    'ALTER TABLE restricciones ADD CONSTRAINT fk_restriccion_subactividad FOREIGN KEY (subactividad_id) REFERENCES subactividades(id) ON DELETE CASCADE',
    'DO 0'
  )
);
PREPARE stmt_restr_sub_fk FROM @sql;
EXECUTE stmt_restr_sub_fk;
DEALLOCATE PREPARE stmt_restr_sub_fk;
