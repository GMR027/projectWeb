-- Curva S: historial de avance por actividad (una fila por actividad y día: el último % de ese día) y avance real capturado a mano por semana
CREATE TABLE IF NOT EXISTS actividad_avance (
    actividad_id INT NOT NULL,
    fecha DATE NOT NULL,
    progreso TINYINT UNSIGNED NOT NULL,
    PRIMARY KEY (actividad_id, fecha),
    FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS curva_s_manual (
    proyecto_id INT NOT NULL,
    fecha DATE NOT NULL,                 -- inicio de la semana
    avance DECIMAL(5,2) NOT NULL,        -- % acumulado real del programa
    PRIMARY KEY (proyecto_id, fecha),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Punto de partida con lo que ya hay: las completadas con fecha fin pasada se fechan en su fin programado (estimado); el resto, hoy
INSERT IGNORE INTO actividad_avance (actividad_id, fecha, progreso)
SELECT id, CASE WHEN progreso = 100 AND fecha_fin < CURDATE() THEN fecha_fin ELSE CURDATE() END, progreso FROM actividades WHERE progreso > 0;
