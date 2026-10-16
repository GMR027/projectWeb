-- Editor: cada gráfico pasa a tener varias láminas (como una presentación). EJECUTAR UNA SOLA VEZ.
-- Las marcas y la imagen que antes guardaba el gráfico pasan a ser su lámina 1.
CREATE TABLE IF NOT EXISTS editor_laminas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    grafico_id INT NOT NULL,
    orden INT NOT NULL DEFAULT 1,
    nombre VARCHAR(60) NOT NULL DEFAULT '',
    marcas_json LONGTEXT,
    marcas_png VARCHAR(100) DEFAULT NULL,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (grafico_id) REFERENCES editor_graficos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO editor_laminas (grafico_id, orden, nombre, marcas_json, marcas_png)
SELECT g.id, 1, '', g.marcas_json, g.marcas_png FROM editor_graficos g
WHERE NOT EXISTS (SELECT 1 FROM editor_laminas l WHERE l.grafico_id = g.id);

ALTER TABLE editor_graficos DROP COLUMN marcas_json, DROP COLUMN marcas_png;
