-- Directorio (Administración): categorías de Punch y Evidencias en un catálogo por proyecto, y departamento/categoría en las evidencias.
-- Los departamentos siguen en bitacora_departamentos; las categorías de Archivo en archivo_categorias; las especialidades en especialidades.
CREATE TABLE IF NOT EXISTS catalogo_categorias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    ambito VARCHAR(20) NOT NULL,   -- punch | evidencias
    nombre VARCHAR(100) NOT NULL,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_cat_ambito (proyecto_id, ambito, nombre),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;
-- Solo si faltan (ver aplicar_migraciones_cluster.sh):
-- ALTER TABLE evidencias ADD COLUMN departamento VARCHAR(100) NOT NULL DEFAULT '', ADD COLUMN categoria VARCHAR(100) NOT NULL DEFAULT '';
