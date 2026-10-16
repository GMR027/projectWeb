-- View360: cada gráfico interactivo se apoya en un gráfico del Editor (lienzo con su fondo: imagen o 1.ª página de un PDF)
ALTER TABLE view360_graficos ADD COLUMN grafico_id INT DEFAULT NULL AFTER version;
ALTER TABLE view360_graficos ADD CONSTRAINT fk_view360_grafico FOREIGN KEY (grafico_id) REFERENCES editor_graficos(id) ON DELETE SET NULL;
