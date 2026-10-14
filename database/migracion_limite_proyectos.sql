-- Límite de proyectos que puede crear un usuario con rol cliente
ALTER TABLE usuarios ADD COLUMN limite_proyectos INT NOT NULL DEFAULT 1 AFTER logo;
UPDATE usuarios SET limite_proyectos = 5 WHERE rol = 'cliente';   -- clientes existentes: 5 proyectos (ajustable desde Usuarios)
