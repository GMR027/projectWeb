-- Navegación compacta: la barra de 96 px se lee mejor con el nombre junto al icono.
-- Idempotente: cambia el valor por defecto para proyectos nuevos y enciende los nombres
-- en los proyectos existentes (cada proyecto puede volver a «No» en Ajustes → Apariencia).
ALTER TABLE proyectos MODIFY nombres_herramientas TINYINT(1) NOT NULL DEFAULT 1;
UPDATE proyectos SET nombres_herramientas = 1 WHERE nombres_herramientas = 0;
