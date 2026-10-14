-- Sección Archivo: categorías y archivos subidos por proyecto
CREATE TABLE IF NOT EXISTS archivo_categorias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_arch_cat (proyecto_id, nombre),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS archivos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    categoria_id INT DEFAULT NULL,
    fecha DATE NOT NULL,
    nombre VARCHAR(200) NOT NULL,
    numero_revision VARCHAR(30) NOT NULL DEFAULT '',
    archivo_original VARCHAR(255) NOT NULL,   -- nombre con el que se subió (se usa al descargar)
    archivo_guardado VARCHAR(100) NOT NULL,   -- nombre interno en storage/archivos/{proyecto}/
    tamano INT UNSIGNED NOT NULL DEFAULT 0,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (categoria_id) REFERENCES archivo_categorias(id) ON DELETE SET NULL
) ENGINE=InnoDB;
