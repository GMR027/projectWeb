-- ============================================================
-- Punch bloquea el cierre
-- Una partida con pendientes altos o críticos abiertos no se puede cerrar
-- hasta resolverlos. Solo admin o dueño pueden cerrar con motivo firmado.
-- ============================================================

--   mysql -u root project < database/migracion_punch_cierre.sql

ALTER TABLE partidas
    ADD COLUMN cerrada TINYINT(1) NOT NULL DEFAULT 0 AFTER fecha_fin,
    ADD COLUMN cerrada_por VARCHAR(150) NOT NULL DEFAULT '' AFTER cerrada,
    ADD COLUMN cerrada_en DATETIME NULL AFTER cerrada_por,
    ADD COLUMN cierre_motivo VARCHAR(500) NOT NULL DEFAULT '' AFTER cerrada_en;
