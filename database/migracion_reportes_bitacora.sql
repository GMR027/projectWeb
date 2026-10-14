-- Reporte diario (con personal) y Bitácora de obra
CREATE TABLE IF NOT EXISTS personal (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    departamento VARCHAR(100) NOT NULL DEFAULT '',
    imagen VARCHAR(100) DEFAULT NULL,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS reportes_diarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    fecha DATE NOT NULL,
    area VARCHAR(150) NOT NULL DEFAULT '',
    comentarios TEXT,
    personal_json LONGTEXT,      -- [id de personal, ...]
    actividades_json LONGTEXT,   -- [id de actividad del programa, ...]
    fotos_json LONGTEXT,         -- [nombre de archivo, ...]
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS bitacora (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    fecha DATE NOT NULL,
    departamento VARCHAR(100) NOT NULL DEFAULT '',
    descripcion TEXT NOT NULL,
    importancia ENUM('baja','media','alta','critica') NOT NULL DEFAULT 'media',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Catálogo de departamentos de la bitácora (el registro guarda el nombre; al renombrar se actualizan los registros)
CREATE TABLE IF NOT EXISTS bitacora_departamentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_bit_depto (proyecto_id, nombre),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;
INSERT IGNORE INTO bitacora_departamentos (proyecto_id, nombre)
    SELECT DISTINCT proyecto_id, departamento FROM bitacora WHERE departamento <> '';
