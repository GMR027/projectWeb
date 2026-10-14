-- Mensajes de contacto (formulario público de la página principal) y de soporte (clientes). Llegan a la bandeja del admin.
CREATE TABLE IF NOT EXISTS contactos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    empresa VARCHAR(150) NOT NULL DEFAULT '',
    puesto VARCHAR(100) NOT NULL DEFAULT '',
    correo VARCHAR(150) NOT NULL,
    telefono VARCHAR(40) NOT NULL DEFAULT '',
    plan VARCHAR(40) NOT NULL DEFAULT '',
    proyectos_aprox VARCHAR(40) NOT NULL DEFAULT '',
    preferencia VARCHAR(20) NOT NULL DEFAULT 'correo',
    origen VARCHAR(60) NOT NULL DEFAULT '',
    mensaje TEXT NOT NULL,
    ip VARCHAR(45) NOT NULL DEFAULT '',
    leido TINYINT(1) NOT NULL DEFAULT 0,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS soportes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    tema VARCHAR(200) NOT NULL,
    informacion TEXT NOT NULL,
    leido TINYINT(1) NOT NULL DEFAULT 0,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;
