-- Apariencia del proyecto: texturas de fondo y halo radial ajustable.
-- Solo agrega columnas con valores por defecto; no toca los datos existentes.
--   tema_textura             ninguna | grano | puntos | vetas | trama | cuadricula | diagonal | papel
--   tema_textura_intensidad  0-100
--   tema_halo_color          vacío = color de acento de la paleta; o #rrggbb
--   tema_halo_intensidad     0-100
--   tema_halo_expansion      50-250 (%)
ALTER TABLE proyectos
    ADD COLUMN tema_textura VARCHAR(20) NOT NULL DEFAULT 'ninguna' AFTER tema_paleta,
    ADD COLUMN tema_textura_intensidad TINYINT UNSIGNED NOT NULL DEFAULT 35 AFTER tema_textura,
    ADD COLUMN tema_halo_color VARCHAR(7) NOT NULL DEFAULT '' AFTER tema_textura_intensidad,
    ADD COLUMN tema_halo_intensidad TINYINT UNSIGNED NOT NULL DEFAULT 45 AFTER tema_halo_color,
    ADD COLUMN tema_halo_expansion SMALLINT UNSIGNED NOT NULL DEFAULT 100 AFTER tema_halo_intensidad;
