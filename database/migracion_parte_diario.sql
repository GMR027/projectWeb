-- ============================================================
-- Parte diario único de campo (cambio 1)
-- Amplía reportes_diarios y agrega las tablas hijas del parte:
-- personal con horas, equipo, materiales, tiempos muertos,
-- seguridad y fotos con leyenda/ubicación.
-- ============================================================

USE project;

ALTER TABLE reportes_diarios
    ADD COLUMN turno ENUM('dia','noche') NOT NULL DEFAULT 'dia' AFTER area,
    ADD COLUMN supervisor VARCHAR(150) NOT NULL DEFAULT '' AFTER turno,
    ADD COLUMN estado ENUM('borrador','cerrado') NOT NULL DEFAULT 'borrador' AFTER comentarios,
    ADD COLUMN clima_precip_mm DECIMAL(6,1) NULL AFTER estado,
    ADD COLUMN clima_viento_kmh DECIMAL(5,1) NULL AFTER clima_precip_mm,
    ADD COLUMN clima_tmax DECIMAL(4,1) NULL AFTER clima_viento_kmh,
    ADD COLUMN clima_tmin DECIMAL(4,1) NULL AFTER clima_tmax,
    ADD COLUMN visitas VARCHAR(500) NOT NULL DEFAULT '' AFTER clima_tmin,
    ADD COLUMN firma_nombre VARCHAR(150) NOT NULL DEFAULT '' AFTER visitas,
    ADD COLUMN firma_imagen VARCHAR(255) NULL AFTER firma_nombre,
    ADD COLUMN offline_uuid VARCHAR(64) NULL AFTER firma_imagen,
    ADD COLUMN creado_por VARCHAR(150) NOT NULL DEFAULT '' AFTER offline_uuid,
    ADD COLUMN cerrado_en DATETIME NULL AFTER creado_por,
    ADD UNIQUE KEY uq_reporte_offline (offline_uuid);

CREATE TABLE IF NOT EXISTS reporte_personal (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reporte_id INT NOT NULL,
    personal_id INT NULL,
    nombre VARCHAR(150) NOT NULL DEFAULT '',
    actividad_id INT NULL,
    horas_normal DECIMAL(5,2) NOT NULL DEFAULT 0,
    horas_extra DECIMAL(5,2) NOT NULL DEFAULT 0,
    cuadrilla VARCHAR(100) NOT NULL DEFAULT '',
    observacion VARCHAR(500) NOT NULL DEFAULT '',
    KEY idx_rp_reporte (reporte_id),
    FOREIGN KEY (reporte_id) REFERENCES reportes_diarios(id) ON DELETE CASCADE,
    FOREIGN KEY (personal_id) REFERENCES personal(id) ON DELETE SET NULL,
    FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS reporte_equipo (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reporte_id INT NOT NULL,
    equipo_id INT NULL,
    nombre VARCHAR(150) NOT NULL DEFAULT '',
    actividad_id INT NULL,
    horas DECIMAL(5,2) NOT NULL DEFAULT 0,
    operador VARCHAR(150) NOT NULL DEFAULT '',
    importe DECIMAL(12,2) NOT NULL DEFAULT 0,
    KEY idx_re_reporte (reporte_id),
    FOREIGN KEY (reporte_id) REFERENCES reportes_diarios(id) ON DELETE CASCADE,
    FOREIGN KEY (equipo_id) REFERENCES equipo(id) ON DELETE SET NULL,
    FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS reporte_materiales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reporte_id INT NOT NULL,
    material_id INT NULL,
    nombre VARCHAR(255) NOT NULL DEFAULT '',
    actividad_id INT NULL,
    cantidad DECIMAL(14,3) NOT NULL DEFAULT 0,
    unidad VARCHAR(30) NOT NULL DEFAULT '',
    tipo ENUM('recibido','consumido','devuelto') NOT NULL DEFAULT 'consumido',
    proveedor VARCHAR(150) NOT NULL DEFAULT '',
    remision VARCHAR(80) NOT NULL DEFAULT '',
    KEY idx_rm_reporte (reporte_id),
    FOREIGN KEY (reporte_id) REFERENCES reportes_diarios(id) ON DELETE CASCADE,
    FOREIGN KEY (material_id) REFERENCES materiales(id) ON DELETE SET NULL,
    FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS reporte_tiempos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reporte_id INT NOT NULL,
    actividad_id INT NULL,
    horas DECIMAL(5,2) NOT NULL DEFAULT 0,
    causa ENUM('lluvia','falta_material','espera','falla_equipo','seguridad','otra') NOT NULL DEFAULT 'otra',
    nota VARCHAR(500) NOT NULL DEFAULT '',
    KEY idx_rt_reporte (reporte_id),
    FOREIGN KEY (reporte_id) REFERENCES reportes_diarios(id) ON DELETE CASCADE,
    FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS reporte_seguridad (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reporte_id INT NOT NULL UNIQUE,
    charla_tema VARCHAR(255) NOT NULL DEFAULT '',
    asistentes SMALLINT NOT NULL DEFAULT 0,
    epp_ok TINYINT(1) NOT NULL DEFAULT 0,
    incidente_id INT NULL,
    FOREIGN KEY (reporte_id) REFERENCES reportes_diarios(id) ON DELETE CASCADE,
    FOREIGN KEY (incidente_id) REFERENCES seguridad_incidentes(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS reporte_fotos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reporte_id INT NOT NULL,
    archivo VARCHAR(255) NOT NULL,
    actividad_id INT NULL,
    leyenda VARCHAR(255) NOT NULL DEFAULT '',
    lat DECIMAL(10,7) NULL,
    lng DECIMAL(10,7) NULL,
    tomada_en DATETIME NULL,
    KEY idx_rf_reporte (reporte_id),
    FOREIGN KEY (reporte_id) REFERENCES reportes_diarios(id) ON DELETE CASCADE,
    FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Enlaces con las herramientas que ya existían: las horas de equipo y los
-- incidentes de seguridad se ligan al parte en vez de capturarse dos veces.
ALTER TABLE equipo_horas
    ADD COLUMN reporte_id INT NULL AFTER actividad_id,
    ADD KEY idx_eh_reporte (reporte_id),
    ADD CONSTRAINT fk_eh_reporte FOREIGN KEY (reporte_id) REFERENCES reportes_diarios(id) ON DELETE SET NULL;

ALTER TABLE seguridad_incidentes
    ADD COLUMN reporte_id INT NULL AFTER actividad_id,
    ADD KEY idx_si_reporte (reporte_id),
    ADD CONSTRAINT fk_si_reporte FOREIGN KEY (reporte_id) REFERENCES reportes_diarios(id) ON DELETE SET NULL;

ALTER TABLE bitacora
    ADD COLUMN reporte_id INT NULL AFTER fecha,
    ADD KEY idx_bit_reporte (reporte_id),
    ADD CONSTRAINT fk_bit_reporte FOREIGN KEY (reporte_id) REFERENCES reportes_diarios(id) ON DELETE SET NULL;

-- Costo del día: tarifa por hora del personal y costo unitario del material.
ALTER TABLE personal
    ADD COLUMN tarifa_hora DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER departamento;

ALTER TABLE materiales
    ADD COLUMN costo_unitario DECIMAL(14,2) NULL AFTER cantidad;
