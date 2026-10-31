-- Ajustes → Apariencia del proyecto → «Apariencia de menú superior».
-- clásico: iconos agrupados por sección (como hasta ahora).
-- panel:   sin barra de iconos; el menú lateral queda fijo.
-- pestanas: los iconos se acomodan por sección en pestañas.
ALTER TABLE proyectos ADD COLUMN menu_superior ENUM('clasico','panel','pestanas') NOT NULL DEFAULT 'clasico' AFTER nombres_herramientas;
