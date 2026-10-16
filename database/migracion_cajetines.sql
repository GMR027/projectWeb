-- Cajetines (título del dibujo) reutilizables por proyecto, para insertar en el Editor y en el editor del vPlan
CREATE TABLE IF NOT EXISTS cajetines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    orientacion ENUM('vertical','banda') NOT NULL DEFAULT 'vertical',
    ancho TINYINT UNSIGNED NOT NULL DEFAULT 28,   -- % del ancho del dibujo
    filas_json TEXT,                              -- [["Etiqueta","valor con {variables}"], ...]
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;
