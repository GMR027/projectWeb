-- Mostrar subactividades en el programa y las vistas de planeación.
-- Ajustes → General → Subactividades. 0 = ocultas por defecto (recomendado);
-- cada vista puede activarlas con su propio check, recordado en el navegador.
-- Idempotente: se puede ejecutar aunque la columna ya exista.
SET @sql = (
  SELECT IF(
    (SELECT COUNT(*) FROM information_schema.columns
      WHERE table_schema = DATABASE() AND table_name = 'proyectos' AND column_name = 'mostrar_subactividades') = 0,
    'ALTER TABLE proyectos ADD COLUMN mostrar_subactividades TINYINT(1) NOT NULL DEFAULT 0',
    'DO 0'
  )
);
PREPARE stmt_subactividades FROM @sql;
EXECUTE stmt_subactividades;
DEALLOCATE PREPARE stmt_subactividades;
