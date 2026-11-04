-- ============================================================
-- Seguridad como sistema: ATS, investigación de incidentes e indicadores
-- ============================================================

--   mysql -u root project < database/migracion_seguridad_sistema.sql

ALTER TABLE seguridad_incidentes
    ADD COLUMN causa_inmediata TEXT NULL AFTER acciones,
    ADD COLUMN causa_raiz TEXT NULL AFTER causa_inmediata,
    ADD COLUMN acciones_correctivas TEXT NULL AFTER causa_raiz,
    ADD COLUMN dias_perdidos INT NOT NULL DEFAULT 0 AFTER acciones_correctivas,
    ADD COLUMN costo DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER dias_perdidos,
    ADD COLUMN investigado_por VARCHAR(150) NOT NULL DEFAULT '' AFTER costo,
    ADD COLUMN cerrado_en DATETIME NULL AFTER investigado_por;

CREATE TABLE IF NOT EXISTS seguridad_ats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    actividad_id INT NULL,
    fecha DATE NOT NULL,
    responsable VARCHAR(150) NOT NULL DEFAULT '',
    trabajo TEXT,
    peligros TEXT,
    controles TEXT,
    epp TEXT,
    participantes INT NOT NULL DEFAULT 0,
    estado ENUM('borrador','firmado','cerrado') NOT NULL DEFAULT 'borrador',
    usuario VARCHAR(150) NOT NULL DEFAULT '',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_ats_proyecto (proyecto_id, fecha, estado),
    KEY idx_ats_actividad (actividad_id, fecha),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS seguridad_ats_firmas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ats_id INT NOT NULL,
    nombre VARCHAR(150) NOT NULL DEFAULT '',
    puesto VARCHAR(100) NOT NULL DEFAULT '',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_atsf_ats (ats_id),
    FOREIGN KEY (ats_id) REFERENCES seguridad_ats(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS seguridad_acciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    incidente_id INT NOT NULL,
    fecha DATE NULL,
    accion TEXT,
    responsable VARCHAR(150) NOT NULL DEFAULT '',
    verificada TINYINT(1) NOT NULL DEFAULT 0,
    notas VARCHAR(500) NOT NULL DEFAULT '',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_sa_incidente (incidente_id),
    FOREIGN KEY (incidente_id) REFERENCES seguridad_incidentes(id) ON DELETE CASCADE
) ENGINE=InnoDB;
