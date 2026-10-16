-- Respuestas del administrador a los mensajes de soporte de los clientes (con fotos adjuntas)
CREATE TABLE IF NOT EXISTS soporte_respuestas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    soporte_id INT NOT NULL,
    titulo VARCHAR(200) NOT NULL,
    descripcion TEXT NOT NULL,
    fecha DATETIME NOT NULL,
    FOREIGN KEY (soporte_id) REFERENCES soportes(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS soporte_respuesta_fotos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    respuesta_id INT NOT NULL,
    archivo VARCHAR(100) NOT NULL,
    FOREIGN KEY (respuesta_id) REFERENCES soporte_respuestas(id) ON DELETE CASCADE
) ENGINE=InnoDB;

