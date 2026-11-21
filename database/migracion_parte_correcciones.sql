-- Corrección de partes diarios cerrados (app y web)
-- 1) Quién creó el parte (id, además del nombre que ya se guardaba).
-- 2) Historial de correcciones: quién, cuándo y por qué.
-- Es repetible: solo agrega la columna si falta.

SET @hay_col := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE() AND table_name = 'reportes_diarios' AND column_name = 'creado_por_id'
);
SET @sql_col := IF(@hay_col = 0,
  'ALTER TABLE reportes_diarios ADD COLUMN creado_por_id INT NULL AFTER creado_por',
  'SELECT 1');
PREPARE stmt_col FROM @sql_col;
EXECUTE stmt_col;
DEALLOCATE PREPARE stmt_col;

SET @hay_idx := (
  SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema = DATABASE() AND table_name = 'reportes_diarios' AND index_name = 'idx_reporte_creado_por'
);
SET @sql_idx := IF(@hay_idx = 0,
  'ALTER TABLE reportes_diarios ADD KEY idx_reporte_creado_por (creado_por_id)',
  'SELECT 1');
PREPARE stmt_idx FROM @sql_idx;
EXECUTE stmt_idx;
DEALLOCATE PREPARE stmt_idx;

CREATE TABLE IF NOT EXISTS parte_correcciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reporte_id INT NOT NULL,
    usuario_id INT NULL,
    usuario VARCHAR(150) NOT NULL DEFAULT '',
    motivo TEXT NOT NULL,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_pc_reporte (reporte_id, creado),
    FOREIGN KEY (reporte_id) REFERENCES reportes_diarios(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Backfill: solo cuando el nombre del creador identifica a un único usuario.
UPDATE reportes_diarios r
JOIN (
    SELECT nombre, MIN(id) id
    FROM usuarios
    GROUP BY nombre
    HAVING COUNT(*) = 1
) u ON u.nombre = r.creado_por
SET r.creado_por_id = u.id
WHERE r.creado_por_id IS NULL AND r.creado_por <> '';
