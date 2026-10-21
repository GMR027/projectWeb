-- vPlan: tablas conectadas al módulo Punch list (vista configurable: columnas, fotos y filtros en `punch_config`).
ALTER TABLE vplan_tablas MODIFY COLUMN tipo ENUM('punch','restricciones','secundarias','otro','punchlist') NOT NULL DEFAULT 'punchlist';
ALTER TABLE vplan_tablas ADD COLUMN punch_config TEXT NULL;
