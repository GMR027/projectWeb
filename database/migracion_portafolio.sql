-- ============================================================
-- Tablero de portafolio: alertas por tendencia de cada obra
-- ============================================================
--   mysql -u root project < database/migracion_portafolio.sql

CREATE TABLE IF NOT EXISTS portafolio_alertas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    clave VARCHAR(40) NOT NULL,
    nivel ENUM('aviso','critico') NOT NULL DEFAULT 'aviso',
    mensaje VARCHAR(255) NOT NULL,
    actualizado TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_portafolio_alerta (proyecto_id, clave),
    KEY idx_pa_actualizado (actualizado),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;
