-- Propuestas del Programador de actividades (IA). Son escenarios aparte: nunca modifican `actividades`.
CREATE TABLE IF NOT EXISTS programador_propuestas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    modelo VARCHAR(150) NOT NULL DEFAULT '',
    fin_actual DATE DEFAULT NULL,
    fin_propuesto DATE DEFAULT NULL,
    resumen_json LONGTEXT NOT NULL,   -- totales, obstáculos y advertencias
    filas_json LONGTEXT NOT NULL,     -- una fila por actividad: actual vs. propuesto + justificación
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;
