-- Normativa y especificaciones: PDFs (español o inglés) que se leen, se parten por cláusulas y se vinculan a materiales y actividades.
CREATE TABLE IF NOT EXISTS normativa_documentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    titulo VARCHAR(200) NOT NULL,
    tipo ENUM('norma','especificacion','otro') NOT NULL DEFAULT 'especificacion',
    codigo VARCHAR(80) NOT NULL DEFAULT '',          -- p. ej. NOM-001-SEDE, CSI 27 15 00
    idioma ENUM('es','en','mixto') NOT NULL DEFAULT 'es',
    archivo VARCHAR(100) NOT NULL,                   -- en storage/normativa/<proyecto>/
    tamano INT UNSIGNED NOT NULL DEFAULT 0,
    paginas INT UNSIGNED NOT NULL DEFAULT 0,
    fragmentos INT UNSIGNED NOT NULL DEFAULT 0,
    estado ENUM('indexado','sin_texto','error') NOT NULL DEFAULT 'indexado',
    nota VARCHAR(300) NOT NULL DEFAULT '',
    creado_por VARCHAR(150) NOT NULL DEFAULT '',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_proyecto (proyecto_id),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS normativa_fragmentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    documento_id INT NOT NULL,
    proyecto_id INT NOT NULL,
    pagina INT UNSIGNED NOT NULL DEFAULT 1,
    clausula VARCHAR(60) NOT NULL DEFAULT '',
    titulo VARCHAR(250) NOT NULL DEFAULT '',
    texto TEXT NOT NULL,
    idioma ENUM('es','en') NOT NULL DEFAULT 'es',
    csi CHAR(6) NULL,                                -- sección tipo CSI (27 15 00 → 271500) si el encabezado la trae
    KEY idx_doc (documento_id),
    KEY idx_csi (proyecto_id, csi),
    FULLTEXT KEY ft_texto (titulo, texto),
    FOREIGN KEY (documento_id) REFERENCES normativa_documentos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS normativa_aplicaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    documento_id INT NULL,                           -- manual: documento (opcional)
    fragmento_id INT NULL,                           -- cláusula concreta, si se conoce
    entidad_tipo ENUM('actividad','material') NOT NULL,
    entidad_id INT NOT NULL,
    referencia VARCHAR(200) NOT NULL DEFAULT '',     -- texto libre en vinculaciones manuales (p. ej. «NOM-001 art. 250»)
    requisito TEXT NULL,                             -- resumen del requisito (en español)
    nota VARCHAR(500) NOT NULL DEFAULT '',
    origen ENUM('manual','csi','palabras','ia') NOT NULL,
    estado ENUM('propuesta','aceptada','rechazada') NOT NULL DEFAULT 'propuesta',
    puntaje DECIMAL(5,2) NOT NULL DEFAULT 0,
    modelo VARCHAR(150) NOT NULL DEFAULT '',
    resuelto_por VARCHAR(150) NOT NULL DEFAULT '',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_entidad (proyecto_id, entidad_tipo, entidad_id),
    KEY idx_estado (proyecto_id, estado),
    UNIQUE KEY uq_vinculo (fragmento_id, entidad_tipo, entidad_id),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (documento_id) REFERENCES normativa_documentos(id) ON DELETE CASCADE,
    FOREIGN KEY (fragmento_id) REFERENCES normativa_fragmentos(id) ON DELETE CASCADE
) ENGINE=InnoDB;
