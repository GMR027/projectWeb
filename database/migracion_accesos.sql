-- Registro de accesos (auditoría mínima de sesión) y cambio obligatorio de contraseña inicial.
-- Idempotente: se puede aplicar sobre una base existente sin perder datos.

CREATE TABLE IF NOT EXISTS accesos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NULL,
    email VARCHAR(100) NOT NULL DEFAULT '',
    ip VARCHAR(45) NOT NULL DEFAULT '',
    agente VARCHAR(255) NOT NULL DEFAULT '',
    resultado ENUM('ok', 'fallo', 'bloqueado', 'demo') NOT NULL DEFAULT 'ok',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_accesos_creado (creado),
    KEY idx_accesos_email (email),
    KEY idx_accesos_ip (ip)
) ENGINE=InnoDB;
