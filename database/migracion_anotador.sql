-- Anotador de planos y documentos: documento base, anotaciones, versiones y registro de firmas.
-- Aplicar con: mysql -u root project < database/migracion_anotador.sql

CREATE TABLE IF NOT EXISTS anotador_documentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    descripcion VARCHAR(500) NOT NULL DEFAULT '',
    fuente_tipo VARCHAR(20) NOT NULL DEFAULT 'archivo',   -- archivo|vplan|editor|redactor|subido
    fuente_id INT NULL,
    archivo VARCHAR(100) NULL,                            -- copia del documento externo (public/uploads/anotador)
    nombre_archivo VARCHAR(255) NOT NULL DEFAULT '',
    ext VARCHAR(10) NOT NULL DEFAULT '',
    paginas INT NOT NULL DEFAULT 1,
    config_json TEXT NOT NULL,                            -- escalas por página, unidad, finalización
    anotaciones_json MEDIUMTEXT NOT NULL,                 -- {ancho, alto, escalas, unidad, shapes}
    miniatura VARCHAR(100) NULL,
    creado_por VARCHAR(150) NOT NULL DEFAULT '',
    usuario_id INT NULL,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_ant_proyecto (proyecto_id, actualizado),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS anotador_versiones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    documento_id INT NOT NULL,
    nombre VARCHAR(150) NOT NULL DEFAULT '',
    config_json TEXT NOT NULL,
    anotaciones_json MEDIUMTEXT NOT NULL,
    miniatura VARCHAR(100) NULL,
    creado_por VARCHAR(150) NOT NULL DEFAULT '',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ant_ver (documento_id, id),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (documento_id) REFERENCES anotador_documentos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS anotador_firmas_aplicadas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    documento_id INT NOT NULL,
    usuario_id INT NULL,
    nombre VARCHAR(150) NOT NULL DEFAULT '',
    cargo VARCHAR(150) NOT NULL DEFAULT '',
    pagina INT NOT NULL DEFAULT 1,
    hash CHAR(64) NOT NULL DEFAULT '',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ant_firma (documento_id, id),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (documento_id) REFERENCES anotador_documentos(id) ON DELETE CASCADE
) ENGINE=InnoDB;
