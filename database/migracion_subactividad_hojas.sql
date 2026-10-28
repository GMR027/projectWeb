-- Vínculo entre una actividad (sus subactividades) y una tabla de SheetFlow.
-- La tabla guarda en su JSON el identificador de cada subactividad por renglón
-- (meta.subactividad.ids) y el rol de las columnas base (cols[].rol), así los
-- cambios de nombre, fechas y avance se sincronizan en los dos sentidos.
CREATE TABLE IF NOT EXISTS subactividad_hojas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    actividad_id INT NOT NULL,
    hoja_id INT NOT NULL,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_subhoja_actividad (actividad_id),
    UNIQUE KEY uq_subhoja_hoja (hoja_id),
    KEY idx_subhoja_proyecto (proyecto_id),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE CASCADE,
    FOREIGN KEY (hoja_id) REFERENCES sheetflow_hojas(id) ON DELETE CASCADE
) ENGINE=InnoDB;
