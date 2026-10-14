-- Nombre de la empresa en las propiedades del proyecto
ALTER TABLE proyectos ADD COLUMN empresa VARCHAR(200) DEFAULT NULL AFTER ubicacion;
