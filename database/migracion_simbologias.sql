-- Simbologías: gráficos del Editor marcados como simbología (leyenda editable) y su uso en las láminas del vPlan
ALTER TABLE editor_graficos ADD COLUMN tipo VARCHAR(12) NOT NULL DEFAULT 'grafico' AFTER nombre;
ALTER TABLE vplan_laminas ADD COLUMN simbologia_grafico_id INT DEFAULT NULL AFTER simbologia;
ALTER TABLE vplan_laminas ADD CONSTRAINT fk_lamina_simbologia FOREIGN KEY (simbologia_grafico_id) REFERENCES editor_graficos(id) ON DELETE SET NULL;
