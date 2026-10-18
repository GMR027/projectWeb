-- Redactor: comentarios, control de cambios, firmas, plantillas propias
ALTER TABLE redactor_documentos
    ADD COLUMN rastrear TINYINT(1) NOT NULL DEFAULT 0 AFTER encabezado,          -- control de cambios activo
    ADD COLUMN comentarios LONGTEXT NULL AFTER contenido;                          -- JSON de comentarios (Classes\Redactor::limpiarComentarios)

-- Firmas manuscritas guardadas por usuario (imagen en uploads/firmas)
CREATE TABLE IF NOT EXISTS redactor_firmas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    nombre VARCHAR(60) NOT NULL,
    archivo VARCHAR(40) NOT NULL,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_usuario (usuario_id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Sellos de firma electrónica interna: quién firmó, cuándo y la huella del texto e imágenes en ese momento
CREATE TABLE IF NOT EXISTS redactor_sellos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    documento_id INT NOT NULL,
    usuario_id INT DEFAULT NULL,
    nombre VARCHAR(100) NOT NULL,
    cargo VARCHAR(100) NOT NULL DEFAULT '',
    huella CHAR(64) NOT NULL,
    firmado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_doc (documento_id),
    FOREIGN KEY (documento_id) REFERENCES redactor_documentos(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Plantillas de documento creadas por el proyecto
CREATE TABLE IF NOT EXISTS redactor_plantillas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    descripcion VARCHAR(200) NOT NULL DEFAULT '',
    papel VARCHAR(10) NOT NULL DEFAULT 'carta',
    orientacion VARCHAR(10) NOT NULL DEFAULT 'vertical',
    margen_sup DECIMAL(4,2) NOT NULL DEFAULT 2.54,
    margen_inf DECIMAL(4,2) NOT NULL DEFAULT 2.54,
    margen_izq DECIMAL(4,2) NOT NULL DEFAULT 2.54,
    margen_der DECIMAL(4,2) NOT NULL DEFAULT 2.54,
    contenido LONGTEXT,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;
