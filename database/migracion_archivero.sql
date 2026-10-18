-- Archivo: carpetas del archivero (virtuales, administradas por el usuario)
CREATE TABLE IF NOT EXISTS archivo_carpetas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_arch_carpeta (proyecto_id, nombre),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

ALTER TABLE archivos ADD COLUMN carpeta_id INT DEFAULT NULL AFTER categoria_id;
ALTER TABLE archivos ADD CONSTRAINT fk_archivos_carpeta FOREIGN KEY (carpeta_id) REFERENCES archivo_carpetas(id) ON DELETE SET NULL;
