-- Espacio (MB) que puede ocupar cada proyecto de un cliente; lo ajusta el admin en Usuarios
ALTER TABLE usuarios ADD COLUMN limite_almacenamiento_mb INT NOT NULL DEFAULT 300 AFTER limite_proyectos;
