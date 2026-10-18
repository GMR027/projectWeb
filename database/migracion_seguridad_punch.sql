-- Seguridad e higiene (permisos de trabajo, incidentes) y punch list (pendientes de calidad con fotos antes/después).
CREATE TABLE IF NOT EXISTS permisos_trabajo (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    tipo VARCHAR(30) NOT NULL,                       -- altura | caliente | espacio_confinado | excavacion | electrico | izaje | general
    titulo VARCHAR(200) NOT NULL,
    actividad_id INT NULL,
    area VARCHAR(150) NOT NULL DEFAULT '',
    responsable VARCHAR(150) NOT NULL DEFAULT '',
    contratista VARCHAR(150) NOT NULL DEFAULT '',
    descripcion TEXT NULL,
    riesgos TEXT NULL,
    medidas_json TEXT NULL,                          -- [{texto, ok}]
    vigente_desde DATE NOT NULL,
    vigente_hasta DATE NOT NULL,
    estado ENUM('borrador','solicitado','aprobado','rechazado','cerrado') NOT NULL DEFAULT 'solicitado',
    origen ENUM('manual','ia') NOT NULL DEFAULT 'manual',
    solicitado_por VARCHAR(150) NOT NULL DEFAULT '',
    resuelto_por VARCHAR(150) NOT NULL DEFAULT '',
    resuelto_en DATETIME NULL,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_proy (proyecto_id, estado, vigente_hasta),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS seguridad_incidentes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    fecha DATE NOT NULL,
    tipo ENUM('incidente','casi_accidente','acto_inseguro','condicion_insegura') NOT NULL,
    severidad ENUM('baja','media','alta','critica') NOT NULL DEFAULT 'media',
    area VARCHAR(150) NOT NULL DEFAULT '',
    actividad_id INT NULL,
    descripcion TEXT NOT NULL,
    acciones TEXT NULL,
    estado ENUM('abierto','cerrado') NOT NULL DEFAULT 'abierto',
    reportado_por VARCHAR(150) NOT NULL DEFAULT '',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_proy (proyecto_id, fecha),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS punch_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    titulo VARCHAR(200) NOT NULL,
    descripcion TEXT NULL,
    actividad_id INT NULL,
    ubicacion VARCHAR(200) NOT NULL DEFAULT '',
    categoria VARCHAR(60) NOT NULL DEFAULT '',
    severidad ENUM('baja','media','alta','critica') NOT NULL DEFAULT 'media',
    responsable VARCHAR(150) NOT NULL DEFAULT '',
    contratista VARCHAR(150) NOT NULL DEFAULT '',
    fecha_limite DATE NULL,
    estado ENUM('abierto','en_proceso','corregido','cerrado','rechazado') NOT NULL DEFAULT 'abierto',
    foto_antes VARCHAR(100) NULL,
    foto_despues VARCHAR(100) NULL,
    origen ENUM('manual','inspeccion','ia') NOT NULL DEFAULT 'manual',
    creado_por VARCHAR(150) NOT NULL DEFAULT '',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    cerrado_en DATETIME NULL,
    KEY idx_proy (proyecto_id, estado),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE SET NULL
) ENGINE=InnoDB;
