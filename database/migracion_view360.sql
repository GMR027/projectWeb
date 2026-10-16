-- View360: gráficos interactivos con enlaces a vistas 360 del proyecto
CREATE TABLE IF NOT EXISTS view360_graficos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    fecha DATE NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    version VARCHAR(30) NOT NULL DEFAULT '',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;
