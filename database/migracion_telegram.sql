-- Telegram (bot de la plataforma): vínculo de cada persona con su chat, destinos de avisos por proyecto y estado de la conversación.
-- Las columnas de proyectos (clima_auto, clima_ultimo, clima_aviso) se agregan con el comprobador `col` de aplicar_migraciones_cluster.sh:
-- ALTER TABLE proyectos ADD COLUMN clima_auto TINYINT(1) NOT NULL DEFAULT 0;
-- ALTER TABLE proyectos ADD COLUMN clima_ultimo DATE NULL;
-- ALTER TABLE proyectos ADD COLUMN clima_aviso DATE NULL;

CREATE TABLE IF NOT EXISTS telegram_vinculos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    chat_id BIGINT NOT NULL,
    nombre_tg VARCHAR(150) NOT NULL DEFAULT '',
    proyecto_id INT NULL,                          -- proyecto activo para /avance, /foto, /nota
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tg_usuario (usuario_id),
    UNIQUE KEY uq_tg_chat (chat_id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Códigos de un solo uso (vencen en 15 min) para vincular un chat privado (tipo usuario) o un grupo a un proyecto (tipo grupo)
CREATE TABLE IF NOT EXISTS telegram_codigos (
    codigo CHAR(12) PRIMARY KEY,
    usuario_id INT NOT NULL,
    tipo ENUM('usuario','grupo') NOT NULL DEFAULT 'usuario',
    proyecto_id INT NULL,
    expira DATETIME NOT NULL,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Dónde se avisa de cada proyecto: un grupo o el chat privado de una persona (usuario_id)
CREATE TABLE IF NOT EXISTS telegram_destinos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    chat_id BIGINT NOT NULL,
    titulo VARCHAR(150) NOT NULL DEFAULT '',
    usuario_id INT NULL,                           -- NULL = grupo
    eventos VARCHAR(500) NOT NULL DEFAULT '',      -- lista separada por comas
    activo TINYINT(1) NOT NULL DEFAULT 1,
    fallos_seguidos INT NOT NULL DEFAULT 0,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tg_destino (proyecto_id, chat_id),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Conversación de cada chat privado (paso, datos temporales) y último update atendido (evita repetir si Telegram reintenta)
CREATE TABLE IF NOT EXISTS telegram_estado (
    chat_id BIGINT PRIMARY KEY,
    paso VARCHAR(20) NOT NULL DEFAULT '',
    datos TEXT NULL,
    ultimo_update BIGINT NOT NULL DEFAULT 0,
    actualizado DATETIME NOT NULL
) ENGINE=InnoDB;
