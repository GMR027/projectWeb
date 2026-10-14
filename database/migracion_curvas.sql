-- Curvas de productividad
CREATE TABLE IF NOT EXISTS curvas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    nombre VARCHAR(200) NOT NULL,
    material_id INT DEFAULT NULL,
    cantidad INT NOT NULL DEFAULT 0,
    unidad VARCHAR(20) NOT NULL DEFAULT 'm',
    tipo_curva ENUM('lineal','final','inicio','campana') NOT NULL DEFAULT 'campana',
    fecha_inicio DATE NOT NULL,
    fecha_fin DATE NOT NULL,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (material_id) REFERENCES materiales(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS curva_reales (
    curva_id INT NOT NULL,
    periodo INT NOT NULL,
    valor DECIMAL(14,2) DEFAULT NULL,
    PRIMARY KEY (curva_id, periodo),
    FOREIGN KEY (curva_id) REFERENCES curvas(id) ON DELETE CASCADE
) ENGINE=InnoDB;
