-- ============================================================
-- Fechas reales, duración restante, restricciones y calendarios propios
-- ============================================================

--   mysql -u root project < database/migracion_fechas_reales_calendarios.sql

CREATE TABLE IF NOT EXISTS calendarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    nombre VARCHAR(120) NOT NULL DEFAULT '',
    activo TINYINT(1) NOT NULL DEFAULT 1,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_cal_proyecto (proyecto_id, activo),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS calendarios_jornada (
    id INT AUTO_INCREMENT PRIMARY KEY,
    calendario_id INT NOT NULL,
    dia_semana TINYINT NOT NULL,
    factor DECIMAL(3,2) NOT NULL DEFAULT 1.00,
    UNIQUE KEY uq_cal_jornada (calendario_id, dia_semana),
    FOREIGN KEY (calendario_id) REFERENCES calendarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS calendarios_festivos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    calendario_id INT NOT NULL,
    fecha DATE NOT NULL,
    motivo VARCHAR(200) NOT NULL DEFAULT '',
    tipo ENUM('festivo','paro_tecnico','clima','otro') NOT NULL DEFAULT 'festivo',
    UNIQUE KEY uq_cal_festivo (calendario_id, fecha),
    FOREIGN KEY (calendario_id) REFERENCES calendarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

ALTER TABLE actividades
    ADD COLUMN calendario_id INT NULL AFTER partida_id,
    ADD COLUMN fecha_inicio_real DATE NULL AFTER fecha_fin,
    ADD COLUMN fecha_fin_real DATE NULL AFTER fecha_inicio_real,
    ADD COLUMN duracion_restante DECIMAL(6,2) NULL AFTER fecha_fin_real,
    ADD COLUMN restriccion_tipo ENUM('ninguna','no_antes','no_despues','terminar_antes') NOT NULL DEFAULT 'ninguna' AFTER duracion_restante,
    ADD COLUMN restriccion_fecha DATE NULL AFTER restriccion_tipo,
    ADD COLUMN restriccion_motivo VARCHAR(500) NOT NULL DEFAULT '' AFTER restriccion_fecha,
    ADD KEY idx_act_calendario (calendario_id);
