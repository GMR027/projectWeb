-- Asistente: conversaciones, mensajes y borradores (Fase 2).
-- Retención: conversaciones 6 meses desde su creación; borradores 30 días.

CREATE TABLE IF NOT EXISTS asistente_conversaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    usuario_id INT NOT NULL,
    uuid VARCHAR(64) NULL,
    titulo VARCHAR(200) NOT NULL DEFAULT 'Asistente',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    archivada TINYINT(1) NOT NULL DEFAULT 0,
    eliminada_en DATETIME NULL,
    UNIQUE KEY uq_asistente_conv_uuid (uuid),
    KEY idx_asistente_conv_usuario (usuario_id, proyecto_id, actualizado),
    KEY idx_asistente_conv_proyecto (proyecto_id, actualizado),
    KEY idx_asistente_conv_creado (creado),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS asistente_mensajes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conversacion_id INT NOT NULL,
    uuid VARCHAR(64) NULL,
    rol ENUM('user','assistant','system','tool') NOT NULL DEFAULT 'user',
    contenido MEDIUMTEXT NOT NULL,
    datos_json TEXT NULL,
    modelo VARCHAR(150) NOT NULL DEFAULT '',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    eliminado_en DATETIME NULL,
    UNIQUE KEY uq_asistente_msg_uuid (uuid),
    KEY idx_asistente_msg_conv (conversacion_id, creado),
    FOREIGN KEY (conversacion_id) REFERENCES asistente_conversaciones(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS asistente_borradores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    usuario_id INT NOT NULL,
    conversacion_id INT NULL,
    uuid VARCHAR(64) NULL,
    tipo ENUM('punch','rfi','bitacora','reporte','minuta') NOT NULL,
    datos_json TEXT NOT NULL,
    estado ENUM('pendiente','aplicado','rechazado','expirado') NOT NULL DEFAULT 'pendiente',
    entidad_id INT NULL,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    aplicado_en DATETIME NULL,
    expira_en DATETIME NULL,
    UNIQUE KEY uq_asistente_borrador_uuid (uuid),
    KEY idx_asistente_borrador (proyecto_id, usuario_id, estado, creado),
    KEY idx_asistente_borrador_expira (estado, expira_en),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (conversacion_id) REFERENCES asistente_conversaciones(id) ON DELETE SET NULL
) ENGINE=InnoDB;
