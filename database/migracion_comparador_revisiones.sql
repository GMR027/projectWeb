-- Comparador de documentos: historial de versiones y enlace público de solo lectura.
-- Ejecutar una sola vez sobre instalaciones existentes (el esquema nuevo ya las incluye).

ALTER TABLE comparador_guardados
    ADD COLUMN token CHAR(48) NULL AFTER usuario_id,
    ADD UNIQUE KEY uq_cmp_token (token);

CREATE TABLE IF NOT EXISTS comparador_versiones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    guardado_id INT NOT NULL,
    nombre VARCHAR(150) NOT NULL DEFAULT '',
    descripcion VARCHAR(500) NOT NULL DEFAULT '',
    vista_json TEXT NOT NULL,
    ajuste_json TEXT NOT NULL,
    modo_json TEXT NOT NULL,
    anotaciones_json MEDIUMTEXT NOT NULL,
    creado_por VARCHAR(150) NOT NULL DEFAULT '',
    usuario_id INT NULL,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_cmp_ver (guardado_id, creado),
    FOREIGN KEY (guardado_id) REFERENCES comparador_guardados(id) ON DELETE CASCADE
) ENGINE=InnoDB;
