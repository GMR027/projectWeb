-- Editor: categorías de gráficos (para clasificar y filtrar los gráficos de un proyecto)
CREATE TABLE IF NOT EXISTS editor_categorias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;
ALTER TABLE editor_graficos ADD COLUMN categoria_id INT DEFAULT NULL AFTER nombre;
ALTER TABLE editor_graficos ADD CONSTRAINT fk_grafico_categoria FOREIGN KEY (categoria_id) REFERENCES editor_categorias(id) ON DELETE SET NULL;
