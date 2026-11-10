-- ============================================================
-- Organizaciones: archivado
-- Archivar oculta la organización de la lista y de los selectores sin borrar
-- sus obras, usuarios ni pagos. Se aplica una sola vez.
-- ============================================================

ALTER TABLE organizaciones
    ADD COLUMN archivada TINYINT(1) NOT NULL DEFAULT 0 AFTER marca_color2,
    ADD COLUMN archivada_en DATETIME NULL AFTER archivada,
    ADD KEY idx_org_archivada (archivada);
