-- vPlan: una lámina puede mostrar solo una parte de las actividades de su partida.
-- Sin filas para la lámina = se muestra la partida completa (comportamiento anterior).
CREATE TABLE IF NOT EXISTS vplan_lamina_actividades (
    lamina_id INT NOT NULL,
    actividad_id INT NOT NULL,
    PRIMARY KEY (lamina_id, actividad_id),
    FOREIGN KEY (lamina_id) REFERENCES vplan_laminas(id) ON DELETE CASCADE,
    FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE CASCADE
) ENGINE=InnoDB;
