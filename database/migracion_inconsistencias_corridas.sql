-- Análisis de inconsistencias guardables: cada corrida agrupa sus hallazgos para consultarlos y resolverlos después.
CREATE TABLE IF NOT EXISTS inconsistencias_corridas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    nombre VARCHAR(150) NOT NULL DEFAULT '',
    guardada TINYINT(1) NOT NULL DEFAULT 0,       -- las no guardadas rotan (se conservan las 5 últimas)
    modelo VARCHAR(150) NOT NULL DEFAULT '',
    del_sistema INT NOT NULL DEFAULT 0,
    de_la_ia INT NOT NULL DEFAULT 0,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

ALTER TABLE inconsistencias ADD COLUMN corrida_id INT NULL AFTER proyecto_id;
INSERT INTO inconsistencias_corridas (proyecto_id, nombre, guardada) SELECT DISTINCT proyecto_id, 'Análisis anterior', 1 FROM inconsistencias;
UPDATE inconsistencias i JOIN inconsistencias_corridas c ON c.proyecto_id = i.proyecto_id SET i.corrida_id = c.id WHERE i.corrida_id IS NULL;
ALTER TABLE inconsistencias MODIFY corrida_id INT NOT NULL;
ALTER TABLE inconsistencias DROP INDEX uq_inconsistencia;
ALTER TABLE inconsistencias ADD UNIQUE KEY uq_corrida_huella (corrida_id, huella);
ALTER TABLE inconsistencias ADD CONSTRAINT fk_inc_corrida FOREIGN KEY (corrida_id) REFERENCES inconsistencias_corridas(id) ON DELETE CASCADE;
