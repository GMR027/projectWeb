-- Foto diaria del espacio usado en los volúmenes (archivos y base de datos) para estimar cuándo se llenan
CREATE TABLE IF NOT EXISTS almacenamiento_historial (
    fecha DATE PRIMARY KEY,
    archivos_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
    bd_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB;
