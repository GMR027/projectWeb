-- Conexiones de IA que el admin usa desde la app de escritorio (Project Web Desktop). No se guardan API keys.
CREATE TABLE IF NOT EXISTS herramientas_ia (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    tipo VARCHAR(20) NOT NULL,
    url VARCHAR(255) NOT NULL DEFAULT '',
    modelo VARCHAR(100) NOT NULL DEFAULT '',
    notas VARCHAR(255) NOT NULL DEFAULT '',
    activa TINYINT(1) NOT NULL DEFAULT 1,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
