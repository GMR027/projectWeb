-- Límite de intentos de acceso (fuerza bruta): 5 fallos por IP + correo en 15 minutos
-- bloquean ese par durante 15 minutos. Es idempotente.
CREATE TABLE IF NOT EXISTS login_intentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip VARCHAR(45) NOT NULL,
    email VARCHAR(100) NOT NULL DEFAULT '',
    intentos INT NOT NULL DEFAULT 0,
    ultimo INT NOT NULL DEFAULT 0,
    bloqueado_hasta INT NOT NULL DEFAULT 0,
    UNIQUE KEY uq_login_ip_email (ip, email),
    KEY idx_login_ultimo (ultimo)
) ENGINE=InnoDB;
