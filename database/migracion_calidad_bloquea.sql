-- ============================================================
-- Compuerta de calidad: la inspección detiene el avance
-- Un plan de calidad puede bloquear el avance de su actividad:
--   · con puntos pendientes, la actividad se topa en 99 % (no se puede cerrar)
--   · con una no conformidad abierta, el avance se congela
-- El dueño del proyecto o el admin pueden liberar una actividad con motivo.
-- ============================================================

USE project;

-- Un plan informativo (0) no detiene nada; un ITP de retención (1) sí.
ALTER TABLE calidad_planes
    ADD COLUMN bloquea TINYINT(1) NOT NULL DEFAULT 1 AFTER estado;

CREATE TABLE IF NOT EXISTS calidad_liberaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    actividad_id INT NOT NULL,
    plan_id INT NULL,
    progreso_autorizado INT NOT NULL DEFAULT 100,
    nc_snapshot INT NOT NULL DEFAULT 0,   -- no conformidades abiertas al liberar: si aparece una nueva, la liberación caduca
    motivo VARCHAR(500) NOT NULL DEFAULT '',
    usuario VARCHAR(150) NOT NULL DEFAULT '',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_clib_actividad (proyecto_id, actividad_id, id),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE CASCADE,
    FOREIGN KEY (plan_id) REFERENCES calidad_planes(id) ON DELETE SET NULL
) ENGINE=InnoDB;
