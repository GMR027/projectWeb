-- vPlan: lienzo de tamaño libre y láminas hechas de bloques posicionables (título, plano, tareas de varias partidas,
-- tablas, simbología, supervisor, fecha, textos). Las láminas existentes se convierten solas al abrirlas
-- (VplanLamina::bloques) con el mismo diseño que tenían; partida_id queda como "partida principal" opcional.
ALTER TABLE vplan_laminas ADD COLUMN ancho_mm SMALLINT UNSIGNED NOT NULL DEFAULT 432 AFTER supervisor;
ALTER TABLE vplan_laminas ADD COLUMN alto_mm SMALLINT UNSIGNED NOT NULL DEFAULT 279 AFTER ancho_mm;
ALTER TABLE vplan_laminas ADD COLUMN preset VARCHAR(20) NOT NULL DEFAULT 'tabloide' AFTER alto_mm;

CREATE TABLE IF NOT EXISTS vplan_bloques (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lamina_id INT NOT NULL,
    tipo VARCHAR(20) NOT NULL,
    x DECIMAL(7,3) NOT NULL DEFAULT 0,     -- posición y tamaño en % de la hoja
    y DECIMAL(7,3) NOT NULL DEFAULT 0,
    w DECIMAL(7,3) NOT NULL DEFAULT 20,
    h DECIMAL(7,3) NOT NULL DEFAULT 10,
    z INT NOT NULL DEFAULT 0,
    config_json TEXT,
    FOREIGN KEY (lamina_id) REFERENCES vplan_laminas(id) ON DELETE CASCADE
) ENGINE=InnoDB;
