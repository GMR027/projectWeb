-- Partidas propias del MSR (ej. «Material de instalación», «Documentos») con materiales del catálogo, sin submittal
CREATE TABLE IF NOT EXISTS msr_partidas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    msr_id INT NOT NULL,
    titulo VARCHAR(150) NOT NULL,
    orden INT NOT NULL DEFAULT 0,
    FOREIGN KEY (msr_id) REFERENCES msrs(id) ON DELETE CASCADE
) ENGINE=InnoDB;
ALTER TABLE msr_items MODIFY submittal_id INT NULL;
