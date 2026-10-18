-- Análisis de materiales: cada corrida (análisis guardable) agrupa hallazgos con una solución que el admin acepta o rechaza.
CREATE TABLE IF NOT EXISTS analisis_materiales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    nombre VARCHAR(150) NOT NULL DEFAULT '',
    guardada TINYINT(1) NOT NULL DEFAULT 0,       -- las no guardadas rotan (se conservan las 5 últimas)
    modelo VARCHAR(150) NOT NULL DEFAULT '',
    del_sistema INT NOT NULL DEFAULT 0,
    de_la_ia INT NOT NULL DEFAULT 0,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS analisis_materiales_hallazgos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    corrida_id INT NOT NULL,
    huella CHAR(40) NOT NULL,
    categoria VARCHAR(30) NOT NULL,
    severidad ENUM('baja','media','alta','critica') NOT NULL DEFAULT 'media',
    origen ENUM('sistema','ia') NOT NULL,
    titulo VARCHAR(255) NOT NULL,
    descripcion TEXT NOT NULL,
    materiales_json TEXT NULL,                    -- ids de materiales implicados
    submittals_json TEXT NULL,                    -- ids de submittals implicados
    solucion_json TEXT NOT NULL,                  -- {texto, accion, params, esperado}
    estado ENUM('pendiente','aceptada','rechazada') NOT NULL DEFAULT 'pendiente',
    antes_json TEXT NULL,                         -- valores previos a aplicar la solución (para revertir)
    modelo VARCHAR(150) NOT NULL DEFAULT '',
    detectado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resuelto_por VARCHAR(150) NOT NULL DEFAULT '',
    resuelto_en DATETIME NULL,
    UNIQUE KEY uq_corrida_huella (corrida_id, huella),
    KEY idx_estado (proyecto_id, estado),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (corrida_id) REFERENCES analisis_materiales(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Registro de cada lote que revisó la IA en un análisis: materiales cubiertos, qué devolvió y si falló (para decidir y actuar después).
CREATE TABLE IF NOT EXISTS analisis_materiales_lotes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    corrida_id INT NOT NULL,
    orden INT NOT NULL DEFAULT 0,
    titulo VARCHAR(150) NOT NULL,
    estado ENUM('ok','error') NOT NULL DEFAULT 'ok',
    error VARCHAR(500) NOT NULL DEFAULT '',
    materiales_json MEDIUMTEXT NULL,              -- [{id, nombre}] analizados en el lote (nombre al momento del análisis)
    hallazgos_json MEDIUMTEXT NULL,               -- [{titulo, severidad, categoria, accion, materiales}] ya validados
    respuesta_json MEDIUMTEXT NULL,               -- respuesta cruda de la IA (recortada)
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_corrida (corrida_id, orden),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (corrida_id) REFERENCES analisis_materiales(id) ON DELETE CASCADE
) ENGINE=InnoDB;
