-- Resumen de la obra cada mañana (Integraciones → «Resumen de la obra»): evento de Telegram resumen.matutino.
-- Solo es para bases locales/existentes: en instalaciones limpias ya vienen en schema.sql y en el clúster las agrega
-- el comprobador `col` de aplicar_migraciones_cluster.sh (por eso no se meten a ese listado: ya se aplican ahí con guarda).
ALTER TABLE proyectos ADD COLUMN resumen_matutino TINYINT(1) NOT NULL DEFAULT 0 AFTER clima_aviso;
ALTER TABLE proyectos ADD COLUMN resumen_ultimo DATE NULL AFTER resumen_matutino;
