-- ============================================================
-- Tipos de frente configurables (CRUD) para la línea de balance
-- ============================================================
--   mysql -u root project < database/migracion_frentes_tipos.sql

CREATE TABLE IF NOT EXISTS frentes_tipos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    nombre VARCHAR(60) NOT NULL,
    orden INT NOT NULL DEFAULT 0,
    color VARCHAR(7) NOT NULL DEFAULT '#00b4d8',
    activo TINYINT(1) NOT NULL DEFAULT 1,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_frente_tipo (proyecto_id, nombre),
    KEY idx_ft_proyecto (proyecto_id, activo, orden),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Los frentes ya creados conservan su tipo: se pasa el enum a un tipo del catálogo
INSERT IGNORE INTO frentes_tipos (proyecto_id, nombre, orden)
SELECT DISTINCT proyecto_id,
    CASE tipo WHEN 'piso' THEN 'Piso' WHEN 'tramo' THEN 'Tramo' WHEN 'tunel' THEN 'Túnel'
              WHEN 'anillo' THEN 'Anillo' ELSE 'Otro' END,
    FIELD(tipo, 'piso', 'tramo', 'tunel', 'anillo', 'otro')
FROM frentes;

ALTER TABLE frentes ADD COLUMN tipo_id INT NULL AFTER tipo;

UPDATE frentes f
JOIN frentes_tipos t ON t.proyecto_id = f.proyecto_id
    AND t.nombre = CASE f.tipo WHEN 'piso' THEN 'Piso' WHEN 'tramo' THEN 'Tramo' WHEN 'tunel' THEN 'Túnel'
                               WHEN 'anillo' THEN 'Anillo' ELSE 'Otro' END
SET f.tipo_id = t.id;
