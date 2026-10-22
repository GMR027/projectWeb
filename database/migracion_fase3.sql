-- Fase 3: costos y presupuesto, recursos y cuadrillas, RFI, enlaces de solo lectura (cliente y calendario .ics)

-- 3.1 Costos: presupuesto por partida y movimientos (costo real y estimaciones)
CREATE TABLE IF NOT EXISTS costos_partidas (
    partida_id INT NOT NULL PRIMARY KEY,
    proyecto_id INT NOT NULL,
    presupuesto DECIMAL(16,2) NOT NULL DEFAULT 0,
    KEY idx_cp_proyecto (proyecto_id),
    FOREIGN KEY (partida_id) REFERENCES partidas(id) ON DELETE CASCADE,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS costos_movimientos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    partida_id INT NULL,
    tipo ENUM('real','estimacion') NOT NULL DEFAULT 'real',
    fecha DATE NOT NULL,
    concepto VARCHAR(255) NOT NULL DEFAULT '',
    referencia VARCHAR(100) NOT NULL DEFAULT '',     -- factura, número de estimación…
    monto DECIMAL(16,2) NOT NULL DEFAULT 0,
    estado ENUM('borrador','aprobada','pagada') NOT NULL DEFAULT 'aprobada',   -- solo estimaciones
    orden_cambio_id INT NULL,
    usuario VARCHAR(150) NOT NULL DEFAULT '',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_cm_proyecto (proyecto_id, tipo, fecha),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (partida_id) REFERENCES partidas(id) ON DELETE SET NULL,
    FOREIGN KEY (orden_cambio_id) REFERENCES ordenes_cambio(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 3.2 Recursos: personas y cuadrillas, y su asignación a actividades
CREATE TABLE IF NOT EXISTS recursos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    tipo ENUM('persona','cuadrilla') NOT NULL DEFAULT 'persona',
    personal_id INT NULL,                            -- persona del Directorio (opcional)
    integrantes SMALLINT NOT NULL DEFAULT 1,         -- tamaño de la cuadrilla
    horas_dia DECIMAL(4,1) NOT NULL DEFAULT 8,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_rec_proyecto (proyecto_id),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (personal_id) REFERENCES personal(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS actividad_recursos (
    actividad_id INT NOT NULL,
    recurso_id INT NOT NULL,
    dedicacion SMALLINT NOT NULL DEFAULT 100,        -- % de su jornada mientras dura la actividad
    PRIMARY KEY (actividad_id, recurso_id),
    FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE CASCADE,
    FOREIGN KEY (recurso_id) REFERENCES recursos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 3.3 RFI (solicitudes de información)
CREATE TABLE IF NOT EXISTS rfis (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    numero VARCHAR(30) NOT NULL,
    asunto VARCHAR(200) NOT NULL,
    pregunta TEXT,
    respuesta TEXT,
    responsable VARCHAR(150) NOT NULL DEFAULT '',    -- quien debe contestar
    solicitante VARCHAR(150) NOT NULL DEFAULT '',
    prioridad ENUM('baja','media','alta','critica') NOT NULL DEFAULT 'media',
    estado ENUM('abierto','respondido','cerrado') NOT NULL DEFAULT 'abierto',
    fecha DATE NOT NULL,
    fecha_limite DATE NULL,
    fecha_respuesta DATE NULL,
    actividad_id INT NULL,                           -- actividad que bloquea
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_rfi_proyecto (proyecto_id, estado),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- El chip de revisión (✅ ⚠ ❌) también sirve para los RFI
ALTER TABLE revisiones MODIFY COLUMN tipo ENUM('material','actividad','herramienta','rfi') NOT NULL;
ALTER TABLE revisiones_historial MODIFY COLUMN tipo ENUM('material','actividad','herramienta','rfi') NOT NULL;

-- 3.4 y 3.5 Enlaces de solo lectura: para el cliente (temporal) y suscripción de calendario .ics
CREATE TABLE IF NOT EXISTS enlaces_proyecto (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    tipo ENUM('cliente','ics') NOT NULL DEFAULT 'cliente',
    token CHAR(40) NOT NULL,
    nombre VARCHAR(150) NOT NULL DEFAULT '',
    secciones VARCHAR(100) NOT NULL DEFAULT 'avance,fotos,reportes',
    expira DATETIME NULL,
    revocado TINYINT(1) NOT NULL DEFAULT 0,
    accesos INT NOT NULL DEFAULT 0,
    ultimo_acceso DATETIME NULL,
    creado_por VARCHAR(150) NOT NULL DEFAULT '',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_enlace_token (token),
    KEY idx_enlace_proyecto (proyecto_id),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;
