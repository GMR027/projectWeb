-- Herramientas de IA, fase 2: niveles de riesgo por acción, vigilancia en el servidor y medidor de uso del modelo.
CREATE TABLE IF NOT EXISTS ia_ajustes (
    proyecto_id INT PRIMARY KEY,
    riesgo_json TEXT NULL,                            -- {accion: 'permiso'|'simple'}; vacío = valores por defecto
    vigilancia_servidor TINYINT(1) NOT NULL DEFAULT 0,
    vigilancia_minutos INT NOT NULL DEFAULT 60,
    vigilancia_ultima DATETIME NULL,
    vigilancia_origen VARCHAR(12) NOT NULL DEFAULT '',  -- cron | web
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ia_uso (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NULL,
    herramienta VARCHAR(60) NOT NULL,
    modelo VARCHAR(150) NOT NULL DEFAULT '',
    proveedor VARCHAR(30) NOT NULL DEFAULT '',
    duracion_ms INT UNSIGNED NOT NULL DEFAULT 0,
    caracteres_entrada INT UNSIGNED NOT NULL DEFAULT 0,
    caracteres_salida INT UNSIGNED NOT NULL DEFAULT 0,
    exito TINYINT(1) NOT NULL DEFAULT 1,
    usuario VARCHAR(150) NOT NULL DEFAULT '',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_proyecto_fecha (proyecto_id, creado),
    KEY idx_fecha (creado),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;
