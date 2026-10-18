-- Inconsistencias de proyecto: hallazgos (del sistema o de la IA) con una solución propuesta que el admin acepta o rechaza.
CREATE TABLE IF NOT EXISTS inconsistencias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    huella CHAR(40) NOT NULL,                     -- identifica el hallazgo para no repetir lo ya resuelto o rechazado
    categoria VARCHAR(30) NOT NULL,
    severidad ENUM('baja','media','alta','critica') NOT NULL DEFAULT 'media',
    origen ENUM('sistema','ia') NOT NULL,
    titulo VARCHAR(200) NOT NULL,
    descripcion TEXT NOT NULL,
    actividades_json TEXT NULL,                   -- ids de actividades implicadas
    solucion_json TEXT NOT NULL,                  -- {texto, accion, params}
    estado ENUM('pendiente','aceptada','rechazada') NOT NULL DEFAULT 'pendiente',
    antes_json TEXT NULL,                         -- valores previos a aplicar la solución (para revertir)
    modelo VARCHAR(150) NOT NULL DEFAULT '',
    detectado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resuelto_por VARCHAR(150) NOT NULL DEFAULT '',
    resuelto_en DATETIME NULL,
    UNIQUE KEY uq_inconsistencia (proyecto_id, huella),
    KEY idx_estado (proyecto_id, estado),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;
