-- Ensamblador (app de escritorio): documentos analizados por la IA y propuestas para el programa, materiales y submittals
CREATE TABLE IF NOT EXISTS ensamblador_sesiones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    usuario VARCHAR(150) NOT NULL DEFAULT '',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ensamblador_archivos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sesion_id INT NOT NULL,
    proyecto_id INT NOT NULL,
    nombre VARCHAR(255) NOT NULL,
    hash CHAR(64) NOT NULL,
    tamano INT NOT NULL DEFAULT 0,
    paginas INT NOT NULL DEFAULT 0,
    caracteres INT NOT NULL DEFAULT 0,
    texto MEDIUMTEXT,                              -- páginas separadas por \f
    aviso VARCHAR(255) NOT NULL DEFAULT '',
    archivo_id INT DEFAULT NULL,                   -- copia guardada en el Archivero
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ens_arch_sesion (sesion_id),
    FOREIGN KEY (sesion_id) REFERENCES ensamblador_sesiones(id) ON DELETE CASCADE,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ensamblador_propuestas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sesion_id INT NOT NULL,
    proyecto_id INT NOT NULL,
    instruccion VARCHAR(1000) NOT NULL DEFAULT '',
    modos VARCHAR(200) NOT NULL DEFAULT '',
    modelo VARCHAR(150) NOT NULL DEFAULT '',
    items_json MEDIUMTEXT NOT NULL,                -- [{id, tipo, titulo, detalle, confianza, datos, estado, antes}]
    estado ENUM('pendiente','aplicada','revertida','rechazada') NOT NULL DEFAULT 'pendiente',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sesion_id) REFERENCES ensamblador_sesiones(id) ON DELETE CASCADE,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Fechas contractuales (inicio, fin, hitos con penalización) extraídas de contratos y bases de licitación
CREATE TABLE IF NOT EXISTS proyecto_fechas_contrato (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    tipo ENUM('inicio','fin','hito') NOT NULL DEFAULT 'hito',
    nombre VARCHAR(200) NOT NULL DEFAULT '',
    fecha DATE NOT NULL,
    penalizacion VARCHAR(200) NOT NULL DEFAULT '',
    origen VARCHAR(255) NOT NULL DEFAULT '',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_fc_proyecto (proyecto_id),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Cantidades de obra (catálogo de conceptos) por actividad; la Curva S pesa por importe cuando todas las actividades lo tienen
CREATE TABLE IF NOT EXISTS actividad_cantidades (
    actividad_id INT NOT NULL PRIMARY KEY,
    proyecto_id INT NOT NULL,
    clave VARCHAR(60) NOT NULL DEFAULT '',
    concepto VARCHAR(255) NOT NULL DEFAULT '',
    cantidad DECIMAL(14,3) NULL,
    unidad VARCHAR(30) NOT NULL DEFAULT '',
    importe DECIMAL(14,2) NULL,
    origen VARCHAR(255) NOT NULL DEFAULT '',
    KEY idx_ac_proyecto (proyecto_id),
    FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE CASCADE,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Datos de contacto del personal del proyecto (directorios y minutas)
CREATE TABLE IF NOT EXISTS personal_contacto (
    personal_id INT NOT NULL PRIMARY KEY,
    cargo VARCHAR(100) NOT NULL DEFAULT '',
    empresa VARCHAR(150) NOT NULL DEFAULT '',
    telefono VARCHAR(40) NOT NULL DEFAULT '',
    correo VARCHAR(150) NOT NULL DEFAULT '',
    FOREIGN KEY (personal_id) REFERENCES personal(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Responsable de cada actividad (una persona del personal del proyecto)
CREATE TABLE IF NOT EXISTS actividad_responsables (
    actividad_id INT NOT NULL PRIMARY KEY,
    personal_id INT NOT NULL,
    FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE CASCADE,
    FOREIGN KEY (personal_id) REFERENCES personal(id) ON DELETE CASCADE
) ENGINE=InnoDB;
