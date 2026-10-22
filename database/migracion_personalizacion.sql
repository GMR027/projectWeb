-- Fase 4: personalización — diseño de listas, campos personalizados, marca del cliente, plantillas de proyecto, inicio por usuario

-- 4.1 Diseño de columnas (nombre, orden, visibilidad) de Lookahead, Punch, Submittals, Bitácora y Reporte diario
CREATE TABLE IF NOT EXISTS disenos_lista (
    proyecto_id INT NOT NULL,
    herramienta VARCHAR(30) NOT NULL,
    columnas_json TEXT NOT NULL,
    PRIMARY KEY (proyecto_id, herramienta),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 4.2 Campos personalizados por proyecto (Nivel, Frente, Torre…) y sus valores por registro
CREATE TABLE IF NOT EXISTS campos_personalizados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    entidad ENUM('actividad','punch','submittal','bitacora','reporte') NOT NULL,
    nombre VARCHAR(60) NOT NULL,
    tipo ENUM('texto','numero','fecha','lista') NOT NULL DEFAULT 'texto',
    opciones TEXT NULL,                       -- tipo lista: una opción por línea
    orden INT NOT NULL DEFAULT 0,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_cpers (proyecto_id, entidad),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS campos_valores (
    campo_id INT NOT NULL,
    registro_id INT NOT NULL,
    valor VARCHAR(500) NOT NULL DEFAULT '',
    PRIMARY KEY (campo_id, registro_id),
    FOREIGN KEY (campo_id) REFERENCES campos_personalizados(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 4.3 Plantillas de proyecto completas (estructura de un proyecto terminado, lista para crear uno nuevo)
CREATE TABLE IF NOT EXISTS plantillas_proyecto (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    descripcion VARCHAR(500) NOT NULL DEFAULT '',
    compartida TINYINT(1) NOT NULL DEFAULT 0,          -- 1 = la ven todos los que pueden crear proyectos
    origen VARCHAR(200) NOT NULL DEFAULT '',            -- nombre del proyecto del que salió
    resumen_json TEXT NULL,                             -- conteos para mostrar en la lista
    datos LONGTEXT NOT NULL,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_pp_usuario (usuario_id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;
