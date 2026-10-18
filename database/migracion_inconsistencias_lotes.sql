-- Registro de cada lote que revisó la IA en un análisis de inconsistencias: actividades cubiertas, qué devolvió y si falló.
CREATE TABLE IF NOT EXISTS inconsistencias_lotes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    corrida_id INT NOT NULL,
    orden INT NOT NULL DEFAULT 0,
    titulo VARCHAR(150) NOT NULL,
    estado ENUM('ok','error') NOT NULL DEFAULT 'ok',
    error VARCHAR(500) NOT NULL DEFAULT '',
    actividades_json MEDIUMTEXT NULL,             -- [{id, codigo, nombre}] cubiertas por el lote (al momento del análisis)
    hallazgos_json MEDIUMTEXT NULL,               -- [{titulo, severidad, categoria, accion}] ya validados
    respuesta_json MEDIUMTEXT NULL,               -- respuesta cruda de la IA (recortada)
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_corrida (corrida_id, orden),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (corrida_id) REFERENCES inconsistencias_corridas(id) ON DELETE CASCADE
) ENGINE=InnoDB;
