-- Operaciones de la app Android para sincronización offline (Fase 1).
-- El UUID lo genera la app; así reintentar una operación no la duplica.

CREATE TABLE IF NOT EXISTS app_operaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    uuid VARCHAR(64) NOT NULL,
    usuario_id INT NOT NULL,
    proyecto_id INT NOT NULL,
    tipo VARCHAR(40) NOT NULL,
    resultado_json TEXT NULL,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_app_operacion_uuid (uuid),
    KEY idx_app_operacion_usuario (usuario_id, creado),
    KEY idx_app_operacion_proyecto (proyecto_id, creado),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;
