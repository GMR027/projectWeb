-- Herramientas visibles para cada visitante (JSON con las claves permitidas; NULL = todas)
ALTER TABLE usuarios ADD COLUMN herramientas TEXT NULL AFTER creado_por;
