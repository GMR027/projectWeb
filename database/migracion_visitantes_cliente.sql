-- Un cliente puede crear hasta 2 usuarios visitante (solo lectura): se registra quién los creó
ALTER TABLE usuarios
    ADD COLUMN creado_por INT DEFAULT NULL AFTER limite_almacenamiento_mb,
    ADD CONSTRAINT fk_usuarios_creado_por FOREIGN KEY (creado_por) REFERENCES usuarios(id) ON DELETE CASCADE;
