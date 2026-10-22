-- Programa de obra configurable (estilo MSR): nombre del programa, columnas elegidas/renombradas y campos nuevos de la actividad.
-- NULL en programa_columnas_json = diseño clásico (las columnas de siempre).
ALTER TABLE proyectos ADD COLUMN programa_nombre VARCHAR(200) NULL;
ALTER TABLE proyectos ADD COLUMN programa_columnas_json TEXT NULL;
-- Indicador condicional: {"campo":"progreso","reglas":[{"min":0,"max":0,"color":"#ef4444"},…]}
ALTER TABLE proyectos ADD COLUMN programa_indicador_json TEXT NULL;
ALTER TABLE actividades ADD COLUMN responsable VARCHAR(150) NULL;
ALTER TABLE actividades ADD COLUMN rec_inicio DATE NULL;
ALTER TABLE actividades ADD COLUMN rec_fin DATE NULL;
ALTER TABLE actividades ADD COLUMN libre_texto VARCHAR(255) NULL;
ALTER TABLE actividades ADD COLUMN libre_numero DECIMAL(14,4) NULL;
ALTER TABLE actividades ADD COLUMN libre_fecha DATE NULL;
