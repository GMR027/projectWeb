-- Instalación de materiales en vPlan (2026-10-27)
-- Una tabla de SheetFlow por lámina, con una fila por actividad/subactividad marcada en el plano.
-- La tabla de vPlan es de tipo «materiales» y se imprime como un bloque más de la lámina.

ALTER TABLE vplan_tablas
    MODIFY COLUMN tipo ENUM('punch','restricciones','secundarias','otro','punchlist','materiales')
    NOT NULL DEFAULT 'punchlist';

ALTER TABLE vplan_laminas
    ADD COLUMN materiales_tabla_id INT NULL AFTER marcas_estados_hash,
    ADD CONSTRAINT fk_vplan_lamina_materiales
        FOREIGN KEY (materiales_tabla_id) REFERENCES vplan_tablas(id) ON DELETE SET NULL;
