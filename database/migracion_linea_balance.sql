-- ============================================================
-- Línea de balance (takt) por frentes
-- ============================================================

--   mysql -u root project < database/migracion_linea_balance.sql

CREATE TABLE IF NOT EXISTS frentes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    nombre VARCHAR(150) NOT NULL DEFAULT '',
    tipo ENUM('piso','tramo','tunel','anillo','otro') NOT NULL DEFAULT 'piso',
    orden INT NOT NULL DEFAULT 0,
    color VARCHAR(7) NOT NULL DEFAULT '#00b4d8',
    activo TINYINT(1) NOT NULL DEFAULT 1,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_frentes_proyecto (proyecto_id, activo, orden),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

ALTER TABLE actividades
    ADD COLUMN frente_id INT NULL AFTER calendario_id,
    ADD KEY idx_act_frente (frente_id);
