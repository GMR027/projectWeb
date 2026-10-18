-- Vigilancia continua de anomalías (Inconsistencias): última foto del proyecto para detectar lo que cambió desde la comprobación anterior.
CREATE TABLE IF NOT EXISTS vigilancia_estado (
    proyecto_id INT PRIMARY KEY,
    huellas_json LONGTEXT NOT NULL,        -- hallazgos del sistema vistos en la última comprobación
    metricas_json TEXT NOT NULL,           -- avance global, fin del programa y conteos
    nuevos_json LONGTEXT NULL,             -- lo nuevo (hallazgos y cambios bruscos) de la última comprobación
    revisado DATETIME NOT NULL,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;
