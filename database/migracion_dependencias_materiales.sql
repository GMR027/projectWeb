-- Dependencias de materiales: qué material necesita que otros estén disponibles (entregados o instalados)
-- antes de poder usarse. Se combinan con actividad_materiales (qué materiales consume/instala cada actividad).
-- Ver classes/DependenciasMateriales.php y views/materiales/dependencias.php.

ALTER TABLE proyectos ADD COLUMN modo_dependencias_materiales
    ENUM('apagado','informativo','automatico') NOT NULL DEFAULT 'apagado';

-- Rol del material dentro de la actividad: instala (por defecto) o solo consume.
ALTER TABLE actividad_materiales ADD COLUMN rol
    ENUM('instala','consume') NOT NULL DEFAULT 'instala';

CREATE TABLE IF NOT EXISTS material_dependencias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    predecesor_id INT NOT NULL,   -- material que debe estar disponible primero
    sucesor_id INT NOT NULL,      -- material que lo requiere
    requisito ENUM('entrega','instalacion') NOT NULL DEFAULT 'entrega',
    retraso INT NOT NULL DEFAULT 0,   -- días hábiles después del requisito
    nota VARCHAR(255) NOT NULL DEFAULT '',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_material_dependencia (proyecto_id, predecesor_id, sucesor_id),
    KEY idx_matdep_sucesor (proyecto_id, sucesor_id),
    KEY idx_matdep_predecesor (proyecto_id, predecesor_id),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (predecesor_id) REFERENCES materiales(id) ON DELETE CASCADE,
    FOREIGN KEY (sucesor_id) REFERENCES materiales(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Foto de las fechas antes de cada reprogramación por materiales, para poder deshacer.
CREATE TABLE IF NOT EXISTS material_dependencias_cambios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    cambios_json LONGTEXT NOT NULL,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;
