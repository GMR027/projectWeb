-- Normas, certificaciones y especificaciones por material
-- (búsqueda ampliada de Normativa y propuestas de IA; una fila por atributo)
CREATE TABLE IF NOT EXISTS material_atributos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    material_id INT NOT NULL,
    tipo ENUM('norma','certificacion','especificacion') NOT NULL DEFAULT 'norma',
    codigo VARCHAR(80) NOT NULL DEFAULT '',          -- NOM-063-SCFI, THW-90, IP66…
    titulo VARCHAR(200) NOT NULL DEFAULT '',         -- nombre o descripción
    valor VARCHAR(200) NOT NULL DEFAULT '',           -- valor de la especificación
    unidad VARCHAR(30) NOT NULL DEFAULT '',           -- V, °C, AWG…
    emisor VARCHAR(120) NOT NULL DEFAULT '',          -- NOM, UL, FM, fabricante…
    fuente_url VARCHAR(500) NOT NULL DEFAULT '',
    notas VARCHAR(500) NOT NULL DEFAULT '',
    origen ENUM('manual','ia','documento') NOT NULL DEFAULT 'manual',
    estado ENUM('propuesta','aceptada','rechazada') NOT NULL DEFAULT 'aceptada',
    creado_por VARCHAR(150) NOT NULL DEFAULT '',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_atributo (material_id, tipo, codigo, titulo),
    KEY idx_material (proyecto_id, material_id, estado),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (material_id) REFERENCES materiales(id) ON DELETE CASCADE
) ENGINE=InnoDB;
