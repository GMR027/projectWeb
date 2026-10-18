-- Órdenes de cambio con seguimiento (instalación/retiro, reubicación, demolición, adición) y líneas base del programa.
CREATE TABLE IF NOT EXISTS ordenes_cambio (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    numero VARCHAR(30) NOT NULL DEFAULT '',
    titulo VARCHAR(200) NOT NULL,
    descripcion TEXT NULL,
    solicitante VARCHAR(150) NOT NULL DEFAULT '',
    estado ENUM('borrador','solicitada','aprobada','rechazada','ejecutada','cerrada') NOT NULL DEFAULT 'borrador',
    fecha DATE NOT NULL,
    impacto_costo DECIMAL(14,2) NOT NULL DEFAULT 0,
    impacto_dias INT NOT NULL DEFAULT 0,
    actividad_id INT NULL,                         -- actividad principal afectada (opcional)
    creado_por VARCHAR(150) NOT NULL DEFAULT '',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_oc_proyecto (proyecto_id, estado),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS cambio_partidas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    orden_id INT NOT NULL,
    proyecto_id INT NOT NULL,
    tipo ENUM('instalacion_temporal','instalacion','reubicacion','demolicion','adicion','modificacion') NOT NULL DEFAULT 'modificacion',
    descripcion VARCHAR(300) NOT NULL,
    ubicacion_original VARCHAR(200) NOT NULL DEFAULT '',
    ubicacion_nueva VARCHAR(200) NOT NULL DEFAULT '',
    cantidad DECIMAL(12,2) NULL,
    unidad VARCHAR(30) NOT NULL DEFAULT '',
    actividad_id INT NULL,
    material_id INT NULL,
    requiere_retiro TINYINT(1) NOT NULL DEFAULT 0,
    estado ENUM('pendiente','ejecutado','retirado','verificado','cancelado') NOT NULL DEFAULT 'pendiente',
    fecha_prevista DATE NULL,
    fecha_ejecucion DATE NULL,
    fecha_retiro_prevista DATE NULL,
    fecha_retiro_real DATE NULL,
    notas VARCHAR(500) NOT NULL DEFAULT '',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_cp_proyecto (proyecto_id, estado),
    FOREIGN KEY (orden_id) REFERENCES ordenes_cambio(id) ON DELETE CASCADE,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE SET NULL,
    FOREIGN KEY (material_id) REFERENCES materiales(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Líneas base: foto inmutable del programa (sin llaves foráneas a actividades: la foto sobrevive aunque se borre la actividad)
CREATE TABLE IF NOT EXISTS lineas_base (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    notas VARCHAR(500) NOT NULL DEFAULT '',
    fin_programado DATE NULL,
    total_actividades INT NOT NULL DEFAULT 0,
    criticas_json TEXT NULL,                       -- ids de actividades en ruta crítica al capturar
    creado_por VARCHAR(150) NOT NULL DEFAULT '',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_lb_proyecto (proyecto_id),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS lineas_base_actividades (
    linea_id INT NOT NULL,
    actividad_id INT NOT NULL,
    codigo VARCHAR(50) NOT NULL DEFAULT '',
    nombre VARCHAR(200) NOT NULL DEFAULT '',
    partida VARCHAR(200) NOT NULL DEFAULT '',
    fecha_inicio DATE NOT NULL,
    fecha_fin DATE NOT NULL,
    duracion_dias INT NOT NULL DEFAULT 1,
    progreso TINYINT UNSIGNED NOT NULL DEFAULT 0,
    hito TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (linea_id, actividad_id),
    FOREIGN KEY (linea_id) REFERENCES lineas_base(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS lineas_base_materiales (
    linea_id INT NOT NULL,
    material_id INT NOT NULL,
    nombre VARCHAR(255) NOT NULL DEFAULT '',
    cantidad DECIMAL(12,2) NULL,
    fecha_compra_prog DATE NULL,
    fecha_entrega_prog DATE NULL,
    PRIMARY KEY (linea_id, material_id),
    FOREIGN KEY (linea_id) REFERENCES lineas_base(id) ON DELETE CASCADE
) ENGINE=InnoDB;
