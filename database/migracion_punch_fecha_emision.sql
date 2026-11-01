-- Punch list: guardar la fecha en que se emitió cada pendiente (antes solo existía la fecha límite).
-- Los pendientes ya registrados toman la fecha de creación como fecha de emisión.
ALTER TABLE punch_items ADD COLUMN fecha_emision DATE NULL AFTER contratista;
UPDATE punch_items SET fecha_emision = DATE(creado) WHERE fecha_emision IS NULL;
