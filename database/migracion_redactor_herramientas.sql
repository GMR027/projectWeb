-- Redactor: encabezado y pie personalizados, marca de agua e historial de versiones
ALTER TABLE redactor_documentos
    ADD COLUMN marca_agua VARCHAR(40) NOT NULL DEFAULT '' AFTER rastrear,           -- texto diagonal detrás de cada página (vacío = sin marca)
    ADD COLUMN encabezado_texto VARCHAR(200) NOT NULL DEFAULT '' AFTER marca_agua,  -- vacío = encabezado automático (si está activo)
    ADD COLUMN pie_texto VARCHAR(200) NOT NULL DEFAULT '' AFTER encabezado_texto;

CREATE TABLE IF NOT EXISTS redactor_versiones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    documento_id INT NOT NULL,
    etiqueta VARCHAR(80) NOT NULL DEFAULT '',
    usuario VARCHAR(100) NOT NULL DEFAULT '',
    contenido LONGTEXT,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_documento (documento_id, creado),
    FOREIGN KEY (documento_id) REFERENCES redactor_documentos(id) ON DELETE CASCADE
) ENGINE=InnoDB;
