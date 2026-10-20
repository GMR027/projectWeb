-- Lookahead configurable por proyecto: nombre de la herramienta, nombre del PTA, semanas visibles, título del banner y plantilla de encabezado (estampa de lienzo)
CREATE TABLE IF NOT EXISTS lookahead_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    nombre VARCHAR(60) NOT NULL DEFAULT '',
    pta_nombre VARCHAR(20) NOT NULL DEFAULT '',
    semanas TINYINT NOT NULL DEFAULT 4,          -- semanas que se ven después de la actual (1-8)
    titulo VARCHAR(150) NOT NULL DEFAULT '',     -- banner; admite {semanas} {proyecto} {codigo}
    encabezado_id INT DEFAULT NULL,
    UNIQUE KEY uq_proyecto (proyecto_id),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;
