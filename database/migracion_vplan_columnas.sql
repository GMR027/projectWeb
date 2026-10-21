-- vPlan: columnas de texto propias en los bloques de tareas (valor por lámina, actividad y columna).
CREATE TABLE IF NOT EXISTS vplan_col_texto (
    lamina_id INT NOT NULL,
    actividad_id INT NOT NULL,
    col_id VARCHAR(12) NOT NULL,
    texto VARCHAR(500) NOT NULL DEFAULT '',
    PRIMARY KEY (lamina_id, actividad_id, col_id),
    FOREIGN KEY (lamina_id) REFERENCES vplan_laminas(id) ON DELETE CASCADE,
    FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE CASCADE
) ENGINE=InnoDB;
