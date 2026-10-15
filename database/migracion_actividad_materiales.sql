-- Planificador: materiales que usa cada actividad del programa (la cantidad y unidad viven en el material)
CREATE TABLE IF NOT EXISTS actividad_materiales (
    actividad_id INT NOT NULL,
    material_id INT NOT NULL,
    PRIMARY KEY (actividad_id, material_id),
    FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE CASCADE,
    FOREIGN KEY (material_id) REFERENCES materiales(id) ON DELETE CASCADE
) ENGINE=InnoDB;
