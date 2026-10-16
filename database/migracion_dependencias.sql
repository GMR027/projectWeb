-- Dependencias de obra: relaciones entre actividades, modo del proyecto y fecha fija por actividad
ALTER TABLE proyectos ADD COLUMN modo_dependencias ENUM('apagado','informativo','automatico') NOT NULL DEFAULT 'apagado';
ALTER TABLE actividades ADD COLUMN fecha_fija TINYINT(1) NOT NULL DEFAULT 0;   -- 1 = el modo automático no la mueve

CREATE TABLE IF NOT EXISTS actividad_dependencias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    predecesora_id INT NOT NULL,
    sucesora_id INT NOT NULL,
    tipo ENUM('FS','SS','FF','SF') NOT NULL DEFAULT 'FS',   -- fin-inicio, inicio-inicio, fin-fin, inicio-fin
    retraso INT NOT NULL DEFAULT 0,                         -- días hábiles (negativo = adelanto)
    UNIQUE KEY uq_dependencia (predecesora_id, sucesora_id),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (predecesora_id) REFERENCES actividades(id) ON DELETE CASCADE,
    FOREIGN KEY (sucesora_id) REFERENCES actividades(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Lo que movió cada reprogramación automática, para poder deshacer la última
CREATE TABLE IF NOT EXISTS dependencias_cambios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    cambios_json LONGTEXT NOT NULL,   -- [{id, inicio, fin}] con las fechas ANTERIORES
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;
