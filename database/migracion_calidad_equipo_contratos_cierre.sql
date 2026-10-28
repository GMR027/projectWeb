-- Herramientas de administración de obra: Calidad (ITP), Equipo y maquinaria,
-- Contratos/fianzas/seguros y Cierre de obra. Idempotente.
--
--   mysql -u root project < database/migracion_calidad_equipo_contratos_cierre.sql

-- ============================================================
-- CALIDAD E INSPECCIONES (ITP)
-- ============================================================
CREATE TABLE IF NOT EXISTS calidad_planes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    actividad_id INT NULL,
    codigo VARCHAR(40) NOT NULL DEFAULT '',
    titulo VARCHAR(200) NOT NULL,
    tipo ENUM('itp','inspeccion','prueba') NOT NULL DEFAULT 'itp',
    norma VARCHAR(200) NOT NULL DEFAULT '',
    responsable VARCHAR(150) NOT NULL DEFAULT '',
    fecha DATE NULL,
    estado ENUM('pendiente','en_proceso','conforme','no_conforme','cerrado') NOT NULL DEFAULT 'pendiente',
    notas TEXT,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_calidad_proyecto (proyecto_id, estado, fecha),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS calidad_puntos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    plan_id INT NOT NULL,
    orden INT NOT NULL DEFAULT 0,
    descripcion VARCHAR(500) NOT NULL,
    criterio VARCHAR(300) NOT NULL DEFAULT '',
    resultado ENUM('pendiente','cumple','no_cumple','na') NOT NULL DEFAULT 'pendiente',
    observacion VARCHAR(1000) NOT NULL DEFAULT '',
    fecha DATE NULL,
    responsable VARCHAR(150) NOT NULL DEFAULT '',
    resuelto TINYINT(1) NOT NULL DEFAULT 0,
    KEY idx_calidad_puntos_plan (plan_id, orden),
    FOREIGN KEY (plan_id) REFERENCES calidad_planes(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- EQUIPO Y MAQUINARIA
-- ============================================================
CREATE TABLE IF NOT EXISTS equipo (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    tipo VARCHAR(80) NOT NULL DEFAULT '',
    marca VARCHAR(80) NOT NULL DEFAULT '',
    modelo VARCHAR(80) NOT NULL DEFAULT '',
    serie VARCHAR(80) NOT NULL DEFAULT '',
    propietario VARCHAR(150) NOT NULL DEFAULT '',
    rentado TINYINT(1) NOT NULL DEFAULT 0,
    tarifa_hora DECIMAL(12,2) NOT NULL DEFAULT 0,
    proximo_servicio DATE NULL,
    proximo_servicio_horas INT NULL,
    estado ENUM('operativo','mantenimiento','baja') NOT NULL DEFAULT 'operativo',
    notas VARCHAR(1000) NOT NULL DEFAULT '',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_equipo_proyecto (proyecto_id, estado),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS equipo_horas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    equipo_id INT NOT NULL,
    actividad_id INT NULL,
    fecha DATE NOT NULL,
    horas DECIMAL(6,2) NOT NULL DEFAULT 0,
    operador VARCHAR(150) NOT NULL DEFAULT '',
    observaciones VARCHAR(1000) NOT NULL DEFAULT '',
    importe DECIMAL(12,2) NOT NULL DEFAULT 0,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_equipo_horas (proyecto_id, fecha),
    KEY idx_equipo_horas_equipo (equipo_id, fecha),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (equipo_id) REFERENCES equipo(id) ON DELETE CASCADE,
    FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- CONTRATOS, FIANZAS, SEGUROS Y LICENCIAS
-- ============================================================
CREATE TABLE IF NOT EXISTS contratos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    tipo ENUM('contrato','fianza','seguro','licencia','otro') NOT NULL DEFAULT 'contrato',
    numero VARCHAR(80) NOT NULL DEFAULT '',
    contraparte VARCHAR(200) NOT NULL DEFAULT '',
    objeto VARCHAR(300) NOT NULL DEFAULT '',
    monto DECIMAL(16,2) NOT NULL DEFAULT 0,
    moneda VARCHAR(3) NOT NULL DEFAULT 'MXN',
    inicio DATE NULL,
    vencimiento DATE NULL,
    aviso_dias SMALLINT NOT NULL DEFAULT 30,
    estado ENUM('vigente','cerrado') NOT NULL DEFAULT 'vigente',
    archivo_id INT NULL,
    notas TEXT,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_contratos_proyecto (proyecto_id, estado, vencimiento),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (archivo_id) REFERENCES archivos(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- CIERRE DE OBRA (entrega-recepción)
-- ============================================================
CREATE TABLE IF NOT EXISTS cierre_obra (
    proyecto_id INT NOT NULL PRIMARY KEY,
    fecha_entrega DATE NULL,
    responsable VARCHAR(150) NOT NULL DEFAULT '',
    notas TEXT,
    actualizado TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS cierre_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    categoria ENUM('as_built','garantia','manual','punch','documento','otro') NOT NULL DEFAULT 'documento',
    titulo VARCHAR(250) NOT NULL,
    responsable VARCHAR(150) NOT NULL DEFAULT '',
    fecha_compromiso DATE NULL,
    estado ENUM('pendiente','en_proceso','completado','na') NOT NULL DEFAULT 'pendiente',
    archivo_id INT NULL,
    notas VARCHAR(1000) NOT NULL DEFAULT '',
    orden INT NOT NULL DEFAULT 0,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_cierre_proyecto (proyecto_id, estado),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (archivo_id) REFERENCES archivos(id) ON DELETE SET NULL
) ENGINE=InnoDB;
