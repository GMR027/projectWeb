-- vPlan: notas por lámina (la fecha se pone sola al crearlas)
CREATE TABLE IF NOT EXISTS vplan_notas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lamina_id INT NOT NULL,
    descripcion TEXT NOT NULL,
    fecha DATETIME NOT NULL,
    FOREIGN KEY (lamina_id) REFERENCES vplan_laminas(id) ON DELETE CASCADE
) ENGINE=InnoDB;
