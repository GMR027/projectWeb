-- Láminas del Editor (/graficos) adjuntas a un paquete de submittal. EJECUTAR UNA SOLA VEZ.
CREATE TABLE IF NOT EXISTS submittal_laminas (
    submittal_id INT NOT NULL,
    lamina_id INT NOT NULL,
    orden INT NOT NULL DEFAULT 0,
    PRIMARY KEY (submittal_id, lamina_id),
    FOREIGN KEY (submittal_id) REFERENCES submittals(id) ON DELETE CASCADE,
    FOREIGN KEY (lamina_id) REFERENCES editor_laminas(id) ON DELETE CASCADE
) ENGINE=InnoDB;
