-- vPlan: láminas de plan visual y tablas adicionales
CREATE TABLE IF NOT EXISTS vplan_laminas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    partida_id INT DEFAULT NULL,
    titulo VARCHAR(200) NOT NULL,
    supervisor VARCHAR(200) NOT NULL DEFAULT '',
    plano VARCHAR(100) DEFAULT NULL,
    simbologia VARCHAR(100) DEFAULT NULL,
    marcas_json LONGTEXT,
    marcas_png VARCHAR(100) DEFAULT NULL,
    marcas_estados_hash VARCHAR(64) DEFAULT NULL,
    orden INT NOT NULL DEFAULT 0,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (partida_id) REFERENCES partidas(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS vplan_recovery (
    lamina_id INT NOT NULL,
    actividad_id INT NOT NULL,
    r1_inicio DATE DEFAULT NULL,
    r1_fin DATE DEFAULT NULL,
    r2_inicio DATE DEFAULT NULL,
    r2_fin DATE DEFAULT NULL,
    PRIMARY KEY (lamina_id, actividad_id),
    FOREIGN KEY (lamina_id) REFERENCES vplan_laminas(id) ON DELETE CASCADE,
    FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS vplan_tablas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    titulo VARCHAR(200) NOT NULL,
    tipo ENUM('punch','restricciones','secundarias','otro') NOT NULL DEFAULT 'punch',
    columnas_json TEXT,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS vplan_tabla_filas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tabla_id INT NOT NULL,
    orden INT NOT NULL DEFAULT 0,
    datos LONGTEXT,
    FOREIGN KEY (tabla_id) REFERENCES vplan_tablas(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS vplan_lamina_tablas (
    lamina_id INT NOT NULL,
    tabla_id INT NOT NULL,
    orden INT NOT NULL DEFAULT 0,
    PRIMARY KEY (lamina_id, tabla_id),
    FOREIGN KEY (lamina_id) REFERENCES vplan_laminas(id) ON DELETE CASCADE,
    FOREIGN KEY (tabla_id) REFERENCES vplan_tablas(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Recovery (bits): 1 = columna Recovery, 2 = columna Recovery 02
ALTER TABLE vplan_laminas ADD COLUMN recovery_cols TINYINT NOT NULL DEFAULT 0 AFTER supervisor;

-- 1 = ya se agregó automáticamente la tabla de restricciones del programa
ALTER TABLE vplan_laminas ADD COLUMN restr_auto TINYINT NOT NULL DEFAULT 0 AFTER recovery_cols;
