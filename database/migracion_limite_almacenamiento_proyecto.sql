-- Espacio propio por proyecto: el administrador puede ampliar o reducir los MB
-- asignados a un proyecto desde Ajustes → Datos → Almacenamiento.
-- NULL = el proyecto hereda el límite del cliente dueño (o 300 MB si no hay).
ALTER TABLE proyectos ADD COLUMN limite_almacenamiento_mb INT NULL AFTER marca_color2;
