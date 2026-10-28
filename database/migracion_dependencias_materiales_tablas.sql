-- Solo las tablas (idempotente) para aplicar_migraciones_cluster.sh.
-- Las columnas nuevas las agrega el script con `col`; el archivo completo es migracion_dependencias_materiales.sql.

CREATE TABLE IF NOT EXISTS material_dependencias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    predecesor_id INT NOT NULL,
    sucesor_id INT NOT NULL,
    requisito ENUM('entrega','instalacion') NOT NULL DEFAULT 'entrega',
    retraso INT NOT NULL DEFAULT 0,
    nota VARCHAR(255) NOT NULL DEFAULT '',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_material_dependencia (proyecto_id, predecesor_id, sucesor_id),
    KEY idx_matdep_sucesor (proyecto_id, sucesor_id),
    KEY idx_matdep_predecesor (proyecto_id, predecesor_id),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (predecesor_id) REFERENCES materiales(id) ON DELETE CASCADE,
    FOREIGN KEY (sucesor_id) REFERENCES materiales(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS material_dependencias_cambios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    cambios_json LONGTEXT NOT NULL,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;
