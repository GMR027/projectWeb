-- Redactor: documentos de texto del proyecto (fecha, nombre y departamento), con márgenes, papel e idioma de ortografía
CREATE TABLE IF NOT EXISTS redactor_documentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    fecha DATE NOT NULL,
    departamento VARCHAR(100) NOT NULL DEFAULT '',
    idioma VARCHAR(8) NOT NULL DEFAULT 'es',          -- es, en o es+en (diccionarios del corrector)
    papel VARCHAR(10) NOT NULL DEFAULT 'carta',       -- carta, a4, oficio, tabloide
    orientacion VARCHAR(10) NOT NULL DEFAULT 'vertical',
    margen_sup DECIMAL(4,2) NOT NULL DEFAULT 2.54,    -- cm
    margen_inf DECIMAL(4,2) NOT NULL DEFAULT 2.54,
    margen_izq DECIMAL(4,2) NOT NULL DEFAULT 2.54,
    margen_der DECIMAL(4,2) NOT NULL DEFAULT 2.54,
    numerar TINYINT(1) NOT NULL DEFAULT 1,            -- numerador de página
    encabezado TINYINT(1) NOT NULL DEFAULT 0,         -- encabezado con nombre, departamento y fecha
    contenido LONGTEXT,                               -- HTML ya saneado (Classes\Redactor::limpiar)
    creado_por INT DEFAULT NULL,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_proyecto (proyecto_id, departamento),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Palabras que el usuario agrega al diccionario del corrector (una lista por proyecto)
CREATE TABLE IF NOT EXISTS redactor_palabras (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    palabra VARCHAR(60) NOT NULL,
    UNIQUE KEY uq_palabra (proyecto_id, palabra),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;
