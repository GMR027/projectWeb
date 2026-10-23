-- Configuración única del bot de Telegram: token CIFRADO y @usuario, editables desde la interfaz
-- (Integraciones → Telegram, solo admin). Así rota el token sin tocar el clúster ni reiniciar.
-- Una sola fila (id = 1). El cifrado usa TELEGRAM_KEY (entorno) o storage/secretos/telegram.key
-- (fuera de public, 0600 y gitignore). No toca datos existentes: solo crea esta tabla.
CREATE TABLE IF NOT EXISTS telegram_config (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    token_cifrado MEDIUMBLOB NOT NULL,
    bot_username VARCHAR(100) NOT NULL DEFAULT '',
    actualizado DATETIME NOT NULL,
    actualizado_por VARCHAR(150) NOT NULL DEFAULT ''
) ENGINE=InnoDB;
