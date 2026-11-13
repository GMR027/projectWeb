-- Órdenes de cambio y sus renglones pueden referenciar una subactividad.
-- La actividad_id sigue siendo la actividad padre (para las consultas existentes).
SET @sql = (
  SELECT IF(
    (SELECT COUNT(*) FROM information_schema.columns
      WHERE table_schema = DATABASE() AND table_name = 'ordenes_cambio' AND column_name = 'subactividad_id') = 0,
    'ALTER TABLE ordenes_cambio ADD COLUMN subactividad_id INT NULL AFTER actividad_id',
    'DO 0'
  )
);
PREPARE stmt_oc_sub FROM @sql;
EXECUTE stmt_oc_sub;
DEALLOCATE PREPARE stmt_oc_sub;

SET @sql = (
  SELECT IF(
    (SELECT COUNT(*) FROM information_schema.columns
      WHERE table_schema = DATABASE() AND table_name = 'cambio_partidas' AND column_name = 'subactividad_id') = 0,
    'ALTER TABLE cambio_partidas ADD COLUMN subactividad_id INT NULL AFTER actividad_id',
    'DO 0'
  )
);
PREPARE stmt_cp_sub FROM @sql;
EXECUTE stmt_cp_sub;
DEALLOCATE PREPARE stmt_cp_sub;
