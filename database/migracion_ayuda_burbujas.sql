-- Mensajes emergentes de ayuda (burbujas de src/js/ayuda.js) por proyecto.
-- Ajustes → Apariencia del proyecto → «Mensajes de ayuda». 1 = mostrar (por defecto), 0 = ocultar.
ALTER TABLE proyectos ADD COLUMN ayuda_burbujas TINYINT(1) NOT NULL DEFAULT 1 AFTER menu_superior;
