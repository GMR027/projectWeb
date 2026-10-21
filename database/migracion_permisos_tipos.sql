-- Tipos de permiso de trabajo propios por proyecto (además de los fijos) y «Nombre de permiso».
CREATE TABLE IF NOT EXISTS permisos_tipos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    nombre VARCHAR(120) NOT NULL,
    palabras TEXT NULL,                              -- palabras clave (una por línea) que detectan la actividad
    medidas_json TEXT NULL,                          -- ["medida", ...]
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_proy (proyecto_id),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Solo si falta (ver aplicar_migraciones_cluster.sh):
-- ALTER TABLE permisos_trabajo ADD COLUMN nombre_permiso VARCHAR(150) NOT NULL DEFAULT '' AFTER tipo;
