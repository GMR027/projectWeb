-- Ensamblador de documentos (Documentos y diseño): PDF que une documentos del Redactor y PDF del Archivo.
-- `fuentes` es la receta (JSON [{"t":"redactor"|"archivo","id":N}, ...]); el PDF se genera con las fuentes actuales.
-- `archivo_id` = copia guardada en Archivo, que se regenera sola cuando cambia una fuente (`firma` = huella de las fuentes).
CREATE TABLE IF NOT EXISTS ensambles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    fuentes TEXT NOT NULL,
    archivo_id INT DEFAULT NULL,
    firma CHAR(40) NOT NULL DEFAULT '',
    creado_por INT DEFAULT NULL,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_proyecto (proyecto_id),
    UNIQUE KEY uq_archivo (archivo_id),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (archivo_id) REFERENCES archivos(id) ON DELETE SET NULL
) ENGINE=InnoDB;
