-- La cantidad y la unidad pasan de cada vínculo actividad↔material al propio material
ALTER TABLE materiales
    ADD COLUMN cantidad DECIMAL(12,2) DEFAULT NULL AFTER certificado_calidad,
    ADD COLUMN unidad VARCHAR(30) NOT NULL DEFAULT '' AFTER cantidad;

-- Conserva lo ya capturado: se toma el valor más alto registrado por material
UPDATE materiales m
JOIN (SELECT material_id, MAX(cantidad) AS c, MAX(unidad) AS u FROM actividad_materiales GROUP BY material_id) x ON x.material_id = m.id
SET m.cantidad = x.c, m.unidad = x.u;

ALTER TABLE actividad_materiales DROP COLUMN cantidad, DROP COLUMN unidad;
