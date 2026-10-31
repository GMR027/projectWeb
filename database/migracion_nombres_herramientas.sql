-- Ajustes → Apariencia del proyecto → «Mostrar nombres de herramientas».
-- Guarda si la barra superior de herramientas del proyecto escribe el nombre debajo de cada icono.
-- 0 = solo iconos (como hasta ahora), 1 = icono + nombre.
ALTER TABLE proyectos ADD COLUMN nombres_herramientas TINYINT(1) NOT NULL DEFAULT 0 AFTER tema_paleta;
