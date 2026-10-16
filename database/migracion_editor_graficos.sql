-- Editor: gráficos/láminas libres (mismo editor de marcas que el vPlan, sobre un lienzo en blanco o con fondo de plantilla)
CREATE TABLE IF NOT EXISTS editor_graficos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    ancho INT NOT NULL DEFAULT 1920,
    alto INT NOT NULL DEFAULT 1080,
    fondo VARCHAR(30) NOT NULL DEFAULT 'liso',
    fondo_color VARCHAR(7) NOT NULL DEFAULT '#ffffff',
    marcas_json LONGTEXT,
    marcas_png VARCHAR(100) DEFAULT NULL,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;
