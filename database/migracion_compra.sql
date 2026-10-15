-- Logística de materiales: compra y entrega viven en el catálogo y alimentan el MSR
ALTER TABLE materiales
    ADD COLUMN compra ENUM('pendiente','comprado') NOT NULL DEFAULT 'pendiente' AFTER certificado_calidad,
    ADD COLUMN fecha_compra_prog DATE DEFAULT NULL AFTER compra,
    ADD COLUMN fecha_compra_real DATE DEFAULT NULL AFTER fecha_compra_prog,
    ADD COLUMN fecha_entrega_prog DATE DEFAULT NULL AFTER fecha_compra_real,
    ADD COLUMN fecha_entrega_real DATE DEFAULT NULL AFTER fecha_entrega_prog;
-- Lo ya capturado en el MSR pasa al catálogo (la fecha más temprana por material)
UPDATE materiales m JOIN (SELECT material_id, MIN(fecha_compra_prog) cp, MIN(fecha_compra_real) cr, MIN(fecha_entrega_prog) ep, MIN(fecha_entrega_real) er FROM msr_items GROUP BY material_id) x ON x.material_id = m.id
    SET m.fecha_compra_prog = x.cp, m.fecha_compra_real = x.cr, m.fecha_entrega_prog = x.ep, m.fecha_entrega_real = x.er;
UPDATE materiales SET compra = 'comprado' WHERE fecha_compra_real IS NOT NULL;
