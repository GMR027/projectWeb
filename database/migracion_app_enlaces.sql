-- Puente de sesión app Android → web (código de un solo uso)
-- Aplicar una vez: mysql -u root project < database/migracion_app_enlaces.sql
CREATE TABLE IF NOT EXISTS app_enlaces (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    codigo_hash CHAR(64) NOT NULL,
    ruta VARCHAR(255) NOT NULL DEFAULT '/dashboard',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expira DATETIME NOT NULL,
    usado TINYINT(1) NOT NULL DEFAULT 0,
    usado_en DATETIME NULL,
    UNIQUE KEY uq_enlace_codigo (codigo_hash),
    KEY idx_enlace_usuario (usuario_id, usado, expira),
    KEY idx_enlace_expira (expira)
) ENGINE=InnoDB;
