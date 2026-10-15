-- Estatus del submittal (enviado/rechazado/aprobado) y Real Submittal Date = fecha del submittal
ALTER TABLE submittals ADD COLUMN estatus ENUM('enviado','rechazado','aprobado') NOT NULL DEFAULT 'enviado' AFTER especialidad;
UPDATE msr_items i JOIN submittals s ON s.id = i.submittal_id SET i.fecha_envio_real = s.fecha;
