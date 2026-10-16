-- View360: notas por gráfico interactivo (la fecha se pone sola al crearlas)
CREATE TABLE IF NOT EXISTS view360_notas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    view360_id INT NOT NULL,
    descripcion TEXT NOT NULL,
    fecha DATETIME NOT NULL,
    FOREIGN KEY (view360_id) REFERENCES view360_graficos(id) ON DELETE CASCADE
) ENGINE=InnoDB;
