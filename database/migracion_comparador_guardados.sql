-- Comparador de documentos: comparaciones guardadas (documentos, vista, alineación y anotaciones).
-- Las copias de los documentos externos se guardan en public/uploads/comparador y la miniatura en min/.
CREATE TABLE IF NOT EXISTS comparador_guardados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    descripcion VARCHAR(500) NOT NULL DEFAULT '',
    a_json TEXT NOT NULL,                 -- {tipo: archivo|subido, id, archivo, nombre, ext, pagina}
    b_json TEXT NOT NULL,
    vista_json TEXT NOT NULL,             -- {x, y, k}
    ajuste_json TEXT NOT NULL,            -- {x, y, s, rot} del documento B
    modo_json TEXT NOT NULL,              -- {modo, pos, dir, fade}
    anotaciones_json MEDIUMTEXT NOT NULL, -- {ancho, alto, shapes: [...]}
    miniatura VARCHAR(100) NULL,
    creado_por VARCHAR(150) NOT NULL DEFAULT '',
    usuario_id INT NULL,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_proy (proyecto_id, actualizado),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;
