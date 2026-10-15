-- Marca las actividades que no necesitan material (no aparecen en «Actividades sin materiales»)
ALTER TABLE actividades ADD COLUMN sin_material TINYINT(1) NOT NULL DEFAULT 0 AFTER hito;
