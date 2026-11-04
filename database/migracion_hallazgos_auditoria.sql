-- Auditoría de hallazgos de IA: frescura, motivo de rechazo, evidencia y estado «obsoleta».
-- Se aplica una sola vez por base. En el clúster, database/aplicar_migraciones_cluster.sh
-- agrega las columnas solo si faltan (es idempotente).
ALTER TABLE inconsistencias
    ADD COLUMN motivo_rechazo VARCHAR(60) NOT NULL DEFAULT '' AFTER estado,
    ADD COLUMN evidencia_json TEXT NULL AFTER descripcion,
    ADD COLUMN vigente_hasta DATETIME NULL AFTER detectado,
    MODIFY COLUMN estado ENUM('pendiente','aceptada','rechazada','obsoleta') NOT NULL DEFAULT 'pendiente',
    ADD KEY idx_vigencia (proyecto_id, estado, vigente_hasta);

ALTER TABLE analisis_materiales_hallazgos
    ADD COLUMN motivo_rechazo VARCHAR(60) NOT NULL DEFAULT '' AFTER estado,
    ADD COLUMN evidencia_json TEXT NULL AFTER descripcion,
    ADD COLUMN vigente_hasta DATETIME NULL AFTER detectado,
    MODIFY COLUMN estado ENUM('pendiente','aceptada','rechazada','obsoleta') NOT NULL DEFAULT 'pendiente',
    ADD KEY idx_vigencia (proyecto_id, estado, vigente_hasta);

-- Los hallazgos pendientes anteriores quedan con una vigencia razonable para que no desaparezcan de golpe.
UPDATE inconsistencias SET vigente_hasta = DATE_ADD(detectado, INTERVAL 30 DAY) WHERE estado = 'pendiente' AND vigente_hasta IS NULL;
UPDATE analisis_materiales_hallazgos SET vigente_hasta = DATE_ADD(detectado, INTERVAL 30 DAY) WHERE estado = 'pendiente' AND vigente_hasta IS NULL;
