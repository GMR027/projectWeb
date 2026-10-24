-- Límite de peticiones al webhook del bot por IP (Telegram::limite), para que nadie martille /api/telegram/webhook.
-- Solo es para bases locales/existentes: en instalaciones limpias ya viene en schema.sql y en el clúster la agrega
-- el listado de tablas de aplicar_migraciones_cluster.sh.
CREATE TABLE IF NOT EXISTS telegram_limites (
    ip VARCHAR(45) NOT NULL,              -- IPv4/IPv6 (es un proxy: la IP real viene de X-Forwarded-For vía ip_cliente())
    ventana INT NOT NULL,                 -- minuto epoch (UNIX_TIMESTAMP() DIV 60)
    n INT NOT NULL DEFAULT 0,             -- peticiones de esa IP en esa ventana
    PRIMARY KEY (ip, ventana)
) ENGINE=InnoDB;
