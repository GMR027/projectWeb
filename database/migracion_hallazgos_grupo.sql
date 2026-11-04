-- Agrupación de hallazgos por PROBLEMA (no por categoría): permite ver juntas todas las actividades
-- con la misma regla (fechas invertidas, día no hábil, restricción activa, material duplicado…)
-- y generar un reporte por grupo. El valor lo pone la regla del sistema (p.ej. «fin_antes_inicio»)
-- o, en los hallazgos de la IA, la categoría/acción propuesta.
ALTER TABLE inconsistencias
    ADD COLUMN grupo VARCHAR(60) NOT NULL DEFAULT '' AFTER categoria,
    ADD KEY idx_grupo (proyecto_id, grupo, estado);

UPDATE inconsistencias SET grupo = categoria WHERE grupo = '';

ALTER TABLE analisis_materiales_hallazgos
    ADD COLUMN grupo VARCHAR(60) NOT NULL DEFAULT '' AFTER categoria,
    ADD KEY idx_grupo (proyecto_id, grupo, estado);

UPDATE analisis_materiales_hallazgos SET grupo = categoria WHERE grupo = '';
