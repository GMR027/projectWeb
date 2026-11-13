-- Dependencias que involucran subactividades.
-- Las relaciones actividad ↔ actividad siguen en `actividad_dependencias`.
-- Aquí se guardan las que tocan una subactividad (sub↔sub, sub↔actividad y actividad↔sub).
-- No se usan FK polimórficas a actividades/subactividades: los nodos huérfanos se
-- ignoran al calcular y se limpian al borrar la actividad o la subactividad.
CREATE TABLE IF NOT EXISTS subactividad_dependencias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    pred_tipo ENUM('actividad','subactividad') NOT NULL,
    pred_id INT NOT NULL,
    suc_tipo ENUM('actividad','subactividad') NOT NULL,
    suc_id INT NOT NULL,
    tipo ENUM('FS','SS','FF','SF') NOT NULL DEFAULT 'FS',
    retraso INT NOT NULL DEFAULT 0,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_subdependencia (pred_tipo, pred_id, suc_tipo, suc_id),
    KEY idx_subdependencia_proyecto (proyecto_id),
    KEY idx_subdependencia_pred (pred_tipo, pred_id),
    KEY idx_subdependencia_suc (suc_tipo, suc_id),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;
