-- vPlan: guías del modo diseño (por lámina) y Recovery dinámico (1 o más columnas).
ALTER TABLE vplan_laminas ADD COLUMN guias_json TEXT NULL AFTER marcas_estados_hash;
ALTER TABLE vplan_laminas ADD COLUMN recovery_n TINYINT NOT NULL DEFAULT 0 AFTER recovery_cols;

CREATE TABLE IF NOT EXISTS vplan_recovery_n (
    lamina_id INT NOT NULL,
    actividad_id INT NOT NULL,
    n TINYINT NOT NULL,
    inicio DATE DEFAULT NULL,
    fin DATE DEFAULT NULL,
    PRIMARY KEY (lamina_id, actividad_id, n),
    FOREIGN KEY (lamina_id) REFERENCES vplan_laminas(id) ON DELETE CASCADE,
    FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Antes: máscara de bits (1 = Recovery, 2 = Recovery 02). Ahora: cuántas columnas hay (Recovery, Recovery 02, 03…).
UPDATE vplan_laminas SET recovery_n = (recovery_cols & 1) + ((recovery_cols & 2) >> 1);
-- Solo «Recovery 02» visible: pasa a ser la primera columna
INSERT IGNORE INTO vplan_recovery_n (lamina_id, actividad_id, n, inicio, fin)
    SELECT r.lamina_id, r.actividad_id, 1, r.r2_inicio, r.r2_fin FROM vplan_recovery r JOIN vplan_laminas l ON l.id = r.lamina_id
    WHERE l.recovery_cols = 2 AND (r.r2_inicio IS NOT NULL OR r.r2_fin IS NOT NULL);
INSERT IGNORE INTO vplan_recovery_n (lamina_id, actividad_id, n, inicio, fin)
    SELECT r.lamina_id, r.actividad_id, 1, r.r1_inicio, r.r1_fin FROM vplan_recovery r JOIN vplan_laminas l ON l.id = r.lamina_id
    WHERE l.recovery_cols <> 2 AND (r.r1_inicio IS NOT NULL OR r.r1_fin IS NOT NULL);
INSERT IGNORE INTO vplan_recovery_n (lamina_id, actividad_id, n, inicio, fin)
    SELECT r.lamina_id, r.actividad_id, 2, r.r2_inicio, r.r2_fin FROM vplan_recovery r JOIN vplan_laminas l ON l.id = r.lamina_id
    WHERE l.recovery_cols <> 2 AND (r.r2_inicio IS NOT NULL OR r.r2_fin IS NOT NULL);
