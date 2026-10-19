-- vPlan: varios vPlan por proyecto. Un vPlan (paquete) agrupa láminas (una por área); cada lámina se puede ocultar,
-- ordenar y etiquetar con un grupo (disciplina, nivel…). Las láminas existentes pasan a un vPlan por proyecto.
CREATE TABLE IF NOT EXISTS vplan_paquetes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    nombre VARCHAR(200) NOT NULL,
    orden INT NOT NULL DEFAULT 0,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

ALTER TABLE vplan_laminas ADD COLUMN paquete_id INT NULL AFTER proyecto_id;
ALTER TABLE vplan_laminas ADD COLUMN visible TINYINT NOT NULL DEFAULT 1 AFTER orden;
ALTER TABLE vplan_laminas ADD COLUMN grupo VARCHAR(100) NOT NULL DEFAULT '' AFTER visible;
ALTER TABLE vplan_laminas ADD CONSTRAINT fk_lamina_paquete FOREIGN KEY (paquete_id) REFERENCES vplan_paquetes(id) ON DELETE CASCADE;

INSERT INTO vplan_paquetes (proyecto_id, nombre, orden)
    SELECT DISTINCT proyecto_id, 'vPlan', 0 FROM vplan_laminas;
UPDATE vplan_laminas l JOIN vplan_paquetes p ON p.proyecto_id = l.proyecto_id SET l.paquete_id = p.id WHERE l.paquete_id IS NULL;
