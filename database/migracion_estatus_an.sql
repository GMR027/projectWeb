-- Aprobado con anotaciones (A/AN): estatus de submittal y status AN del MSR
ALTER TABLE submittals MODIFY estatus ENUM('enviado','rechazado','aprobado','aprobado_an') NOT NULL DEFAULT 'enviado';
ALTER TABLE msr_items MODIFY status ENUM('A','AN','P','RA') NOT NULL DEFAULT 'P';
