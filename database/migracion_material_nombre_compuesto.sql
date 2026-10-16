-- Materiales: el nombre se arma con nombre base + marca + modelo/tipo + valor extra.
-- La columna `nombre` sigue guardando el nombre completo (lo usan MSR, planificador, fichas y búsquedas).
ALTER TABLE materiales ADD COLUMN nombre_base VARCHAR(255) DEFAULT NULL AFTER referencia;
ALTER TABLE materiales ADD COLUMN marca VARCHAR(100) NOT NULL DEFAULT '' AFTER nombre_base;
ALTER TABLE materiales ADD COLUMN modelo VARCHAR(100) NOT NULL DEFAULT '' AFTER marca;
ALTER TABLE materiales ADD COLUMN valor_extra VARCHAR(150) NOT NULL DEFAULT '' AFTER modelo;
-- Los materiales existentes conservan su nombre actual como nombre base
UPDATE materiales SET nombre_base = nombre WHERE nombre_base IS NULL;
