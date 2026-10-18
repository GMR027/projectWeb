-- Mapa de avance en planos: el color de las marcas del Editor (rojo/amarillo/verde) se traduce a un porcentaje de avance por actividad.
CREATE TABLE IF NOT EXISTS mapa_avance_ajustes (
    proyecto_id INT PRIMARY KEY,
    rojo TINYINT UNSIGNED NOT NULL DEFAULT 0,        -- % de avance que vale una marca roja (no avanzado)
    amarillo TINYINT UNSIGNED NOT NULL DEFAULT 50,   -- … amarilla (a medias)
    verde TINYINT UNSIGNED NOT NULL DEFAULT 100,     -- … verde (terminado)
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Qué capa de una lámina corresponde a qué actividad (capa_id vacío = todas las marcas sin capa asignada)
CREATE TABLE IF NOT EXISTS mapa_avance_capas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    lamina_id INT NOT NULL,
    capa_id VARCHAR(40) NOT NULL DEFAULT '',
    actividad_id INT NOT NULL,
    UNIQUE KEY uq_capa (lamina_id, capa_id, actividad_id),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (lamina_id) REFERENCES vplan_laminas(id) ON DELETE CASCADE,
    FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Una foto por día de lo que indican las marcas: sirve para ver el avance diario y su tendencia
CREATE TABLE IF NOT EXISTS mapa_avance_instantaneas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    lamina_id INT NOT NULL,
    actividad_id INT NOT NULL,
    fecha DATE NOT NULL,
    porcentaje DECIMAL(5,2) NOT NULL,
    peso DECIMAL(12,2) NOT NULL DEFAULT 0,
    detalle_json TEXT NULL,                          -- marcas rojas/amarillas/verdes
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_dia (lamina_id, actividad_id, fecha),
    KEY idx_act (proyecto_id, actividad_id, fecha),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (lamina_id) REFERENCES vplan_laminas(id) ON DELETE CASCADE,
    FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE CASCADE
) ENGINE=InnoDB;
