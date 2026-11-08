-- ============================================================
-- Planes, organizaciones y obras activas
-- Migración para instalaciones existentes. Se aplica una sola vez.
-- ============================================================

-- 1) Catálogo de planes
CREATE TABLE IF NOT EXISTS planes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    clave VARCHAR(30) NOT NULL UNIQUE,
    nombre VARCHAR(80) NOT NULL,
    precio_mensual DECIMAL(10,2) NOT NULL DEFAULT 0,
    precio_anual DECIMAL(10,2) NOT NULL DEFAULT 0,
    limite_obras INT NULL,
    usuarios_edicion INT NULL,
    limite_visitantes INT NULL,
    almacenamiento_gb INT NULL,
    almacenamiento_por_obra_mb INT NOT NULL DEFAULT 300,
    permite_gestores TINYINT(1) NOT NULL DEFAULT 0,
    features_json TEXT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    orden SMALLINT NOT NULL DEFAULT 0
) ENGINE=InnoDB;

INSERT IGNORE INTO planes (clave, nombre, precio_mensual, precio_anual, limite_obras, usuarios_edicion, limite_visitantes, almacenamiento_gb, almacenamiento_por_obra_mb, permite_gestores, features_json, activo, orden) VALUES
('obra', 'Obra', 390, 3900, 2, 2, NULL, 5, 2048, 0, '[]', 1, 10),
('pro', 'Pro', 990, 9900, 8, 6, NULL, 30, 6144, 1, '["gestores","ia","api"]', 1, 20),
('constructora', 'Constructora', 2490, 24900, 25, 15, NULL, 100, 10240, 1, '["gestores","ia","api","sso","marca"]', 1, 30),
('corporativo', 'Corporativo', 5900, 59000, NULL, NULL, NULL, 300, 25600, 1, '["gestores","ia","api","sso","marca","multiempresa"]', 1, 40),
('legacy', 'Legacy', 0, 0, NULL, NULL, NULL, NULL, 300, 0, '["ia","api","sso","marca","multiempresa"]', 1, 90);

-- 2) Organizaciones
CREATE TABLE IF NOT EXISTS organizaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(200) NOT NULL,
    razon_social VARCHAR(200) NULL,
    rfc VARCHAR(20) NULL,
    correo_facturacion VARCHAR(150) NULL,
    telefono VARCHAR(30) NULL,
    logo VARCHAR(100) NULL,
    propietario_id INT NULL,
    plan_id VARCHAR(30) NOT NULL DEFAULT 'obra',
    estado_plan ENUM('trial','activo','suspendido','cancelado') NOT NULL DEFAULT 'trial',
    trial_hasta DATE NULL,
    plan_inicio DATE NULL,
    plan_fin DATE NULL,
    gracia_hasta DATE NULL,
    precio_legado DECIMAL(10,2) NULL,
    limites_json TEXT NULL,
    almacenamiento_bytes BIGINT NOT NULL DEFAULT 0,
    marca_activa TINYINT(1) NOT NULL DEFAULT 0,
    marca_nombre VARCHAR(150) NULL,
    marca_logo VARCHAR(100) NULL,
    marca_color VARCHAR(7) NULL,
    marca_color2 VARCHAR(7) NULL,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_org_propietario (propietario_id),
    KEY idx_org_plan (plan_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS organizacion_pagos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    organizacion_id INT NOT NULL,
    fecha DATE NOT NULL,
    monto DECIMAL(12,2) NOT NULL DEFAULT 0,
    periodo_inicio DATE NULL,
    periodo_fin DATE NULL,
    metodo VARCHAR(40) NOT NULL DEFAULT '',
    referencia VARCHAR(100) NOT NULL DEFAULT '',
    cfdi_uuid VARCHAR(50) NOT NULL DEFAULT '',
    notas VARCHAR(255) NOT NULL DEFAULT '',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_pagos_org (organizacion_id, fecha),
    FOREIGN KEY (organizacion_id) REFERENCES organizaciones(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 3) Columnas nuevas en usuarios y proyectos
ALTER TABLE usuarios
    ADD COLUMN organizacion_id INT NULL AFTER logo,
    ADD COLUMN rol_org ENUM('propietario','gestor','colaborador','visitante') NOT NULL DEFAULT 'visitante' AFTER organizacion_id,
    ADD COLUMN puede_crear_proyectos TINYINT(1) NOT NULL DEFAULT 0 AFTER rol_org,
    ADD KEY idx_usuarios_org (organizacion_id);

ALTER TABLE proyectos
    ADD COLUMN organizacion_id INT NULL AFTER limite_almacenamiento_mb,
    ADD COLUMN archivado TINYINT(1) NOT NULL DEFAULT 0 AFTER organizacion_id,
    ADD COLUMN archivado_en DATETIME NULL AFTER archivado,
    ADD COLUMN archivado_por INT NULL AFTER archivado_en,
    ADD KEY idx_proyectos_org (organizacion_id, archivado);

-- 4) Organización interna de ProjectWeb (admin, demo y semillas)
INSERT INTO organizaciones (nombre, plan_id, estado_plan, propietario_id, plan_inicio)
SELECT 'ProjectWeb', 'legacy', 'activo', id, CURDATE()
FROM usuarios WHERE email = 'admin@po.local' LIMIT 1;

UPDATE usuarios
SET organizacion_id = (SELECT id FROM organizaciones WHERE nombre = 'ProjectWeb' LIMIT 1),
    rol_org = 'propietario', puede_crear_proyectos = 1
WHERE email = 'admin@po.local';

-- 5) El resto de la migración de datos (una organización por cliente, proyectos y
--    equipo) la hace bin/migrar_organizaciones.php, que es idempotente y valida conteos.
