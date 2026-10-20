-- Estampas inteligentes: bloques reutilizables con etiquetas (prefijo + campo + sufijo) que se llenan solas desde los datos del proyecto
CREATE TABLE IF NOT EXISTS estampas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    config_json LONGTEXT,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_proyecto (proyecto_id),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;
