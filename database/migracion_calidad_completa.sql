-- ============================================================
-- Calidad completa: ensayos, no conformidades con acción/costo y expediente
-- ============================================================

--   mysql -u root project < database/migracion_calidad_completa.sql

CREATE TABLE IF NOT EXISTS calidad_ensayos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    actividad_id INT NULL,
    plan_id INT NULL,
    tipo VARCHAR(100) NOT NULL DEFAULT '',
    muestra VARCHAR(150) NOT NULL DEFAULT '',
    ubicacion VARCHAR(200) NOT NULL DEFAULT '',
    fecha_toma DATE NULL,
    fecha_resultado DATE NULL,
    laboratorio VARCHAR(150) NOT NULL DEFAULT '',
    norma VARCHAR(200) NOT NULL DEFAULT '',
    resultado VARCHAR(100) NOT NULL DEFAULT '',
    veredicto ENUM('pendiente','aprobado','rechazado') NOT NULL DEFAULT 'pendiente',
    archivo_id INT NULL,
    notas TEXT,
    usuario VARCHAR(150) NOT NULL DEFAULT '',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ens_proyecto (proyecto_id, veredicto, fecha_resultado),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE SET NULL,
    FOREIGN KEY (plan_id) REFERENCES calidad_planes(id) ON DELETE SET NULL,
    FOREIGN KEY (archivo_id) REFERENCES archivos(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS calidad_nc (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    actividad_id INT NULL,
    plan_id INT NULL,
    punto_id INT NULL,
    codigo VARCHAR(40) NOT NULL DEFAULT '',
    hallazgo TEXT,
    causa TEXT,
    accion TEXT,
    responsable VARCHAR(150) NOT NULL DEFAULT '',
    fecha_compromiso DATE NULL,
    costo DECIMAL(14,2) NOT NULL DEFAULT 0,
    estado ENUM('abierta','en_correccion','cerrada') NOT NULL DEFAULT 'abierta',
    evidencia_antes INT NULL,
    evidencia_despues INT NULL,
    movimiento_id INT NULL,
    usuario VARCHAR(150) NOT NULL DEFAULT '',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    cerrada_en DATETIME NULL,
    cerrada_por VARCHAR(150) NOT NULL DEFAULT '',
    KEY idx_nc_proyecto (proyecto_id, estado, fecha_compromiso),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE SET NULL,
    FOREIGN KEY (plan_id) REFERENCES calidad_planes(id) ON DELETE SET NULL,
    FOREIGN KEY (punto_id) REFERENCES calidad_puntos(id) ON DELETE SET NULL,
    FOREIGN KEY (evidencia_antes) REFERENCES archivos(id) ON DELETE SET NULL,
    FOREIGN KEY (evidencia_despues) REFERENCES archivos(id) ON DELETE SET NULL,
    FOREIGN KEY (movimiento_id) REFERENCES costos_movimientos(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS calidad_nc_acciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nc_id INT NOT NULL,
    fecha DATE NULL,
    accion TEXT,
    responsable VARCHAR(150) NOT NULL DEFAULT '',
    verificada TINYINT(1) NOT NULL DEFAULT 0,
    notas VARCHAR(500) NOT NULL DEFAULT '',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_nca_nc (nc_id),
    FOREIGN KEY (nc_id) REFERENCES calidad_nc(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS calidad_expedientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    partida_id INT NOT NULL,
    estado ENUM('borrador','cerrado') NOT NULL DEFAULT 'borrador',
    pdf_ruta VARCHAR(255) NOT NULL DEFAULT '',
    huella CHAR(32) NOT NULL DEFAULT '',
    cerrado_en DATETIME NULL,
    cerrado_por VARCHAR(150) NOT NULL DEFAULT '',
    actualizado TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_exp_partida (proyecto_id, partida_id),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (partida_id) REFERENCES partidas(id) ON DELETE CASCADE
) ENGINE=InnoDB;
