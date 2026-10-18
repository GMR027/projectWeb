-- Grabaciones de reuniones (minutas) guardadas en el proyecto. El audio vive en storage/grabaciones/<proyecto>/ (no en public) y cuenta para la cuota.
CREATE TABLE IF NOT EXISTS grabaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    nombre VARCHAR(200) NOT NULL DEFAULT '',
    archivo VARCHAR(100) NOT NULL,
    mime VARCHAR(60) NOT NULL DEFAULT '',
    tamano INT UNSIGNED NOT NULL DEFAULT 0,
    duracion_seg INT UNSIGNED NOT NULL DEFAULT 0,
    transcripcion LONGTEXT NULL,
    creado_por VARCHAR(150) NOT NULL DEFAULT '',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_proyecto (proyecto_id, creado),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;
