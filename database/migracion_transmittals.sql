-- ============================================================
-- Correspondencia y acuses de plano
-- Envíos formales (transmittals) con documentos y revisión congelada,
-- destinatarios y acuse de recibo por enlace público.
-- ============================================================

--   mysql -u root project < database/migracion_transmittals.sql

CREATE TABLE IF NOT EXISTS transmittals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    numero VARCHAR(40) NOT NULL DEFAULT '',
    fecha DATE NOT NULL,
    de VARCHAR(150) NOT NULL DEFAULT '',
    asunto VARCHAR(250) NOT NULL DEFAULT '',
    mensaje TEXT,
    medio ENUM('correo','enlace','impreso','otro') NOT NULL DEFAULT 'correo',
    fecha_limite DATE NULL,
    estado ENUM('borrador','enviado','cerrado') NOT NULL DEFAULT 'enviado',
    usuario VARCHAR(150) NOT NULL DEFAULT '',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_tr_proyecto (proyecto_id, estado, fecha),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS transmittal_documentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transmittal_id INT NOT NULL,
    archivo_id INT NULL,
    nombre VARCHAR(255) NOT NULL DEFAULT '',
    revision VARCHAR(30) NOT NULL DEFAULT '',
    orden INT NOT NULL DEFAULT 0,
    FOREIGN KEY (transmittal_id) REFERENCES transmittals(id) ON DELETE CASCADE,
    FOREIGN KEY (archivo_id) REFERENCES archivos(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS transmittal_destinatarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transmittal_id INT NOT NULL,
    nombre VARCHAR(150) NOT NULL DEFAULT '',
    email VARCHAR(150) NOT NULL DEFAULT '',
    usuario_id INT NULL,
    token CHAR(40) NOT NULL,
    estado ENUM('pendiente','recibido','observado','rechazado') NOT NULL DEFAULT 'pendiente',
    acuse_comentario TEXT,
    acuse_nombre VARCHAR(150) NOT NULL DEFAULT '',
    acuse_fecha DATETIME NULL,
    acuse_ip VARCHAR(45) NOT NULL DEFAULT '',
    UNIQUE KEY uq_tr_token (token),
    KEY idx_tr_dest (transmittal_id, estado),
    FOREIGN KEY (transmittal_id) REFERENCES transmittals(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;
