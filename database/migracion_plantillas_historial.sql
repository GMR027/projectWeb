-- Registro de cada vez que se identificó o se eligió el tipo de obra de un proyecto (referencia futura)
CREATE TABLE IF NOT EXISTS proyecto_plantilla_historial (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    plantilla VARCHAR(30) NOT NULL,
    origen ENUM('identificado','manual') NOT NULL DEFAULT 'manual',
    confianza TINYINT UNSIGNED NOT NULL DEFAULT 0,       -- % de coincidencia al identificar
    indicios VARCHAR(500) NOT NULL DEFAULT '',           -- palabras del proyecto que lo sustentan
    usuario VARCHAR(150) NOT NULL DEFAULT '',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_proyecto (proyecto_id, creado),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;
