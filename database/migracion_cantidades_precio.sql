-- ============================================================
-- Cantidades con precio unitario
-- Cada actividad puede tener su concepto de obra:
--   cantidad × precio unitario = importe
-- Cuando todas las actividades de una partida están valuadas, su presupuesto
-- pasa a ser la suma de los importes; si falta alguna, se conserva el manual.
-- ============================================================

--   mysql -u root project < database/migracion_cantidades_precio.sql

ALTER TABLE actividad_cantidades
    ADD COLUMN precio_unitario DECIMAL(14,2) NULL AFTER cantidad;

-- Lo que ya tenía cantidad e importe conserva su precio unitario
UPDATE actividad_cantidades
   SET precio_unitario = ROUND(importe / NULLIF(cantidad, 0), 2)
 WHERE precio_unitario IS NULL AND cantidad > 0 AND importe IS NOT NULL;

ALTER TABLE costos_partidas
    ADD COLUMN presupuesto_manual DECIMAL(16,2) NULL AFTER presupuesto,
    ADD COLUMN origen ENUM('manual','cantidades') NOT NULL DEFAULT 'manual' AFTER presupuesto_manual;

-- El presupuesto que ya estaba escrito a mano se conserva como respaldo
UPDATE costos_partidas SET presupuesto_manual = presupuesto WHERE presupuesto_manual IS NULL;
