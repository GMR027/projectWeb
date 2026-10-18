-- Revisiones por proyecto: veredicto de normativa/especificaciones (materiales y actividades) y estado de uso de las herramientas de IA.
CREATE TABLE IF NOT EXISTS revisiones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    tipo ENUM('material','actividad','herramienta') NOT NULL,
    ref VARCHAR(60) NOT NULL,                          -- id del material/actividad o clave de la herramienta
    veredicto ENUM('sin_evaluar','correcto','revisar','no_cumple') NOT NULL DEFAULT 'sin_evaluar',
    revisado TINYINT(1) NOT NULL DEFAULT 0,
    huella CHAR(32) NOT NULL DEFAULT '',               -- datos del elemento al revisar; si cambian, la revisión queda desactualizada
    nota VARCHAR(500) NOT NULL DEFAULT '',
    usuario_id INT NULL,
    usuario VARCHAR(150) NOT NULL DEFAULT '',
    ejecutada_en DATETIME NULL,                        -- herramientas: última ejecución
    huella_ejecucion CHAR(32) NOT NULL DEFAULT '',     -- herramientas: datos del proyecto en esa ejecución
    actualizado TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_revision (proyecto_id, tipo, ref),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Historial de cambios del estado: solo se agregan filas
CREATE TABLE IF NOT EXISTS revisiones_historial (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    tipo ENUM('material','actividad','herramienta') NOT NULL,
    ref VARCHAR(60) NOT NULL,
    nombre VARCHAR(255) NOT NULL DEFAULT '',
    veredicto_antes VARCHAR(20) NOT NULL DEFAULT 'sin_evaluar',
    veredicto_despues VARCHAR(20) NOT NULL DEFAULT 'sin_evaluar',
    revisado_antes TINYINT(1) NOT NULL DEFAULT 0,
    revisado_despues TINYINT(1) NOT NULL DEFAULT 0,
    nota VARCHAR(500) NOT NULL DEFAULT '',
    usuario VARCHAR(150) NOT NULL DEFAULT '',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_proyecto_fecha (proyecto_id, creado),
    KEY idx_elemento (proyecto_id, tipo, ref),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;
