-- Informes generados por las herramientas de IA (reporte diario, resumen semanal, lookahead, minutas, cierre…). El contenido se calcula
-- en el servidor; la IA solo redacta. Un informe es un borrador hasta que el admin lo aprueba, y algunos tipos se pueden aplicar a su módulo.
CREATE TABLE IF NOT EXISTS informes_ia (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    tipo VARCHAR(30) NOT NULL,
    titulo VARCHAR(200) NOT NULL,
    parametros_json TEXT NULL,
    contenido_json LONGTEXT NOT NULL,             -- {secciones:[{titulo,tipo,...}], aplicar:{...}}
    origen ENUM('sistema','ia') NOT NULL DEFAULT 'sistema',
    modelo VARCHAR(150) NOT NULL DEFAULT '',
    estado ENUM('borrador','aprobado','descartado') NOT NULL DEFAULT 'borrador',
    aplicado_en DATETIME NULL,
    aplicado_ref VARCHAR(100) NOT NULL DEFAULT '',
    creado_por VARCHAR(150) NOT NULL DEFAULT '',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_proyecto_tipo (proyecto_id, tipo, creado),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;
