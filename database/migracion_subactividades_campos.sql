-- ============================================================
-- Subactividades completas (fase 0)
--
-- La subactividad pasa a tener los mismos campos que una actividad
-- (fechas reales, restricción programada, prioridad, PTA, hito, etc.)
-- y su antiguo `status` numérico (0-100) se convierte en `progreso`
-- + `status` (no_iniciada / en_proceso / completada).
--
--   mysql -u root project < database/migracion_subactividades_campos.sql
--
-- IMPORTANTE: este archivo es para una base que todavía tiene
-- `subactividades.status` numérico. Si tu base ya está migrada, no lo
-- vuelvas a ejecutar. La vía idempotente y recomendada es:
--
--   php bin/migrar_subactividades.php
--
-- Las instalaciones nuevas ya traen el esquema en database/schema.sql.
-- ============================================================

ALTER TABLE subactividades
    CHANGE COLUMN status progreso TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'avance 0-100',
    ADD COLUMN status ENUM('no_iniciada','en_proceso','completada') NOT NULL DEFAULT 'no_iniciada' AFTER progreso,
    ADD COLUMN calendario_id INT NULL AFTER actividad_id,
    ADD COLUMN frente_id INT NULL AFTER calendario_id,
    ADD COLUMN descripcion TEXT NULL AFTER nombre,
    ADD COLUMN fecha_inicio_real DATE NULL AFTER fecha_fin,
    ADD COLUMN fecha_fin_real DATE NULL AFTER fecha_inicio_real,
    ADD COLUMN duracion_restante DECIMAL(6,2) NULL AFTER fecha_fin_real,
    ADD COLUMN restriccion_tipo ENUM('ninguna','no_antes','no_despues','terminar_antes') NOT NULL DEFAULT 'ninguna' AFTER duracion_restante,
    ADD COLUMN restriccion_fecha DATE NULL AFTER restriccion_tipo,
    ADD COLUMN restriccion_motivo VARCHAR(500) NOT NULL DEFAULT '' AFTER restriccion_fecha,
    ADD COLUMN prioridad ENUM('baja','media','alta','critica') NULL DEFAULT 'media' AFTER status,
    ADD COLUMN pta TINYINT(1) NOT NULL DEFAULT 1 AFTER prioridad,
    ADD COLUMN hito TINYINT NOT NULL DEFAULT 0 AFTER pta,
    ADD COLUMN sin_material TINYINT(1) NOT NULL DEFAULT 0 AFTER hito,
    ADD COLUMN orden INT NULL DEFAULT 0 AFTER sin_material,
    ADD COLUMN fecha_fija TINYINT(1) NOT NULL DEFAULT 0 AFTER orden,
    ADD COLUMN responsable VARCHAR(150) NULL AFTER fecha_fija,
    ADD COLUMN rec_inicio DATE NULL AFTER responsable,
    ADD COLUMN rec_fin DATE NULL AFTER rec_inicio,
    ADD COLUMN libre_texto VARCHAR(255) NULL AFTER rec_fin,
    ADD COLUMN libre_numero DECIMAL(14,4) NULL AFTER libre_texto,
    ADD COLUMN libre_fecha DATE NULL AFTER libre_numero,
    ADD COLUMN icono VARCHAR(120) NULL AFTER libre_fecha,
    ADD COLUMN actualizado TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER creado,
    ADD KEY idx_subactividades_actividad_orden (actividad_id, orden);

-- El estado se deriva del avance.
UPDATE subactividades SET status = CASE
    WHEN progreso <= 0 THEN 'no_iniciada'
    WHEN progreso >= 100 THEN 'completada'
    ELSE 'en_proceso' END;

-- Orden estable dentro de cada actividad.
UPDATE subactividades s
JOIN (
    SELECT id, ROW_NUMBER() OVER (PARTITION BY actividad_id ORDER BY fecha_inicio ASC, id ASC) AS rn
    FROM subactividades
) x ON x.id = s.id
SET s.orden = x.rn
WHERE s.orden IS NULL OR s.orden = 0;
