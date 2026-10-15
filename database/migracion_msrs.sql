-- Varios MSR por proyecto: cada paquete de submittal pertenece a un MSR
CREATE TABLE IF NOT EXISTS msrs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    descripcion VARCHAR(500) NOT NULL DEFAULT '',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

ALTER TABLE submittals ADD COLUMN msr_id INT DEFAULT NULL AFTER proyecto_id,
    ADD FOREIGN KEY (msr_id) REFERENCES msrs(id) ON DELETE SET NULL;
ALTER TABLE msr_items ADD COLUMN msr_id INT DEFAULT NULL AFTER proyecto_id,
    ADD FOREIGN KEY (msr_id) REFERENCES msrs(id) ON DELETE CASCADE;

-- Lo existente pasa a un «MSR 1» por proyecto
INSERT INTO msrs (proyecto_id, nombre) SELECT proyecto_id, 'MSR 1' FROM (SELECT proyecto_id FROM submittals UNION SELECT proyecto_id FROM msr_items) t;
UPDATE submittals s JOIN msrs m ON m.proyecto_id = s.proyecto_id SET s.msr_id = m.id;
UPDATE msr_items i JOIN msrs m ON m.proyecto_id = i.proyecto_id SET i.msr_id = m.id;
