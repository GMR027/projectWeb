-- Historial de versiones de las tablas de SheetFlow (Ajustes › Historial de la tabla)
-- Cada guardado con cambios deja una copia del JSON (como mucho una por minuto; «Guardar» siempre).
CREATE TABLE IF NOT EXISTS sheetflow_versiones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hoja_id INT NOT NULL,
    usuario_id INT DEFAULT NULL,
    nombre VARCHAR(150) NOT NULL DEFAULT '',
    datos LONGTEXT,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_hoja (hoja_id, id),
    FOREIGN KEY (hoja_id) REFERENCES sheetflow_hojas(id) ON DELETE CASCADE
) ENGINE=InnoDB;
