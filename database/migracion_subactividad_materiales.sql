-- Materiales propios de una subactividad (independientes de los de su actividad).
CREATE TABLE IF NOT EXISTS subactividad_materiales (
    subactividad_id INT NOT NULL,
    material_id INT NOT NULL,
    rol ENUM('instala','consume') NOT NULL DEFAULT 'instala',
    PRIMARY KEY (subactividad_id, material_id),
    FOREIGN KEY (subactividad_id) REFERENCES subactividades(id) ON DELETE CASCADE,
    FOREIGN KEY (material_id) REFERENCES materiales(id) ON DELETE CASCADE
) ENGINE=InnoDB;
