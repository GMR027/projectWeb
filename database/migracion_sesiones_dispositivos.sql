-- Sesiones y dispositivos activos por usuario.
-- Permite ver desde Ajustes dónde se inició sesión, cuántos dispositivos hay y cerrar los que no se usan.
-- Idempotente: se puede aplicar sobre una base existente sin perder datos.

CREATE TABLE IF NOT EXISTS sesiones_usuario (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    token_hash CHAR(64) NOT NULL,                 -- SHA-256 del token de sesión; el token completo nunca se guarda
    tipo ENUM('web', 'android') NOT NULL DEFAULT 'web',
    dispositivo VARCHAR(120) NOT NULL DEFAULT '', -- etiqueta legible: «Chrome en Windows», «Pixel 8»
    navegador VARCHAR(120) NOT NULL DEFAULT '',
    sistema VARCHAR(80) NOT NULL DEFAULT '',
    ip VARCHAR(45) NOT NULL DEFAULT '',
    agente VARCHAR(255) NOT NULL DEFAULT '',
    app_version VARCHAR(30) NULL,
    push_token VARCHAR(255) NULL,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ultimo_acceso TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expira DATETIME NULL,
    revocada TINYINT(1) NOT NULL DEFAULT 0,
    revocada_en DATETIME NULL,
    revocada_por INT NULL,
    UNIQUE KEY uq_sesion_token (token_hash),
    KEY idx_sesion_usuario (usuario_id, revocada, ultimo_acceso),
    KEY idx_sesion_creado (creado),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;
