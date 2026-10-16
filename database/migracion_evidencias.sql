-- Evidencias fotográficas subidas directamente a una actividad (las de los reportes diarios se leen de ahí)
CREATE TABLE IF NOT EXISTS evidencias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    actividad_id INT DEFAULT NULL,
    fecha DATE NOT NULL,
    descripcion VARCHAR(300) NOT NULL DEFAULT '',
    foto VARCHAR(100) NOT NULL,
    usuario_id INT DEFAULT NULL,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE SET NULL
) ENGINE=InnoDB;
