-- Minutas de junta y personal del contratista
CREATE TABLE IF NOT EXISTS personal_contratista (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    puesto VARCHAR(100) NOT NULL DEFAULT '',
    empresa VARCHAR(150) NOT NULL DEFAULT '',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS minutas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    fecha DATE NOT NULL,
    nombre VARCHAR(200) NOT NULL,
    importancia ENUM('baja','media','alta','critica') NOT NULL DEFAULT 'media',
    contratista_json LONGTEXT,   -- [id de personal_contratista, ...]
    personal_json LONGTEXT,      -- [id de personal del proyecto, ...]
    temas_json LONGTEXT,         -- [{"tema","responsable","departamento"}, ...]
    compromisos_json LONGTEXT,   -- [{"tema","responsable","departamento"}, ...] (opcional)
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;
