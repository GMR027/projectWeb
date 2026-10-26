-- Láminas del Editor (/graficos) adjuntas a un RFI. EJECUTAR UNA SOLA VEZ.
CREATE TABLE IF NOT EXISTS rfi_laminas (
    rfi_id INT NOT NULL,
    lamina_id INT NOT NULL,
    orden INT NOT NULL DEFAULT 0,
    PRIMARY KEY (rfi_id, lamina_id),
    FOREIGN KEY (rfi_id) REFERENCES rfis(id) ON DELETE CASCADE,
    FOREIGN KEY (lamina_id) REFERENCES editor_laminas(id) ON DELETE CASCADE
) ENGINE=InnoDB;
