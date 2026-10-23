-- Clima automático en la bitácora (Integraciones → «Registrar el clima cada día») y aviso del pronóstico (Telegram).
-- Solo es para bases locales/existentes: en instalaciones limpias ya vienen en schema.sql y en el clúster las agrega
-- el comprobador `col` de aplicar_migraciones_cluster.sh (por eso no se meten a ese listado: ya se aplican ahí con guarda).
ALTER TABLE proyectos ADD COLUMN clima_auto TINYINT(1) NOT NULL DEFAULT 0 AFTER tema_paleta;
ALTER TABLE proyectos ADD COLUMN clima_ultimo DATE NULL AFTER clima_auto;
ALTER TABLE proyectos ADD COLUMN clima_aviso DATE NULL AFTER clima_ultimo;
