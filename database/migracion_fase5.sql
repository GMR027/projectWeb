-- Fase 5: API REST (tokens) y webhooks

-- 5.1 Tokens de la API: se guarda solo el hash (SHA-256); el token completo se muestra una vez al crearlo
CREATE TABLE IF NOT EXISTS api_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    token_hash CHAR(64) NOT NULL,
    prefijo VARCHAR(12) NOT NULL,                  -- primeros caracteres, para reconocerlo en la lista
    escritura TINYINT(1) NOT NULL DEFAULT 0,       -- 0 = solo lectura
    proyecto_id INT NULL,                          -- NULL = todos los proyectos del usuario
    expira DATE NULL,
    revocado TINYINT(1) NOT NULL DEFAULT 0,
    usos INT NOT NULL DEFAULT 0,
    ultimo_uso DATETIME NULL,
    ventana INT NOT NULL DEFAULT 0,                -- minuto (epoch/60) del límite de uso
    ventana_n INT NOT NULL DEFAULT 0,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_api_hash (token_hash),
    KEY idx_api_usuario (usuario_id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 5.1 Webhooks: avisos HTTP firmados (HMAC-SHA256) cuando algo cambia en un proyecto
CREATE TABLE IF NOT EXISTS webhooks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    nombre VARCHAR(100) NOT NULL DEFAULT '',
    url VARCHAR(500) NOT NULL,
    secreto CHAR(40) NOT NULL,
    eventos VARCHAR(500) NOT NULL DEFAULT '*',     -- lista separada por comas, o * para todos
    activo TINYINT(1) NOT NULL DEFAULT 1,
    fallos_seguidos INT NOT NULL DEFAULT 0,        -- al llegar a 10 se desactiva solo
    creado_por VARCHAR(150) NOT NULL DEFAULT '',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_wh_proyecto (proyecto_id, activo),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS webhook_entregas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    webhook_id INT NOT NULL,
    evento VARCHAR(60) NOT NULL,
    codigo_http SMALLINT NOT NULL DEFAULT 0,
    ok TINYINT(1) NOT NULL DEFAULT 0,
    ms INT NOT NULL DEFAULT 0,
    error VARCHAR(255) NOT NULL DEFAULT '',
    carga TEXT NULL,                               -- el JSON enviado (para reintentar)
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_whe (webhook_id, id),
    FOREIGN KEY (webhook_id) REFERENCES webhooks(id) ON DELETE CASCADE
) ENGINE=InnoDB;
