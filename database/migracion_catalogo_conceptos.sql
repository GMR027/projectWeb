-- ============================================================
-- Catálogo de conceptos del presupuesto
--
-- El presupuesto deja de depender de «todas las actividades valuadas»: ahora
-- los conceptos viven en el catálogo (cantidad contractual × precio unitario) y
-- se reparten entre una o varias actividades del programa.
--
--   mysql -u root project < database/migracion_catalogo_conceptos.sql
--
-- Este archivo es IDEMPOTENTE: se puede volver a ejecutar sobre una base a medias
-- (por ejemplo, si una vez falló a la mitad) y termina de dejar el esquema completo.
-- Después: php bin/migrar_conceptos.php   (convierte las cantidades actuales)
-- ============================================================

-- 1) Columna de ajuste (solo si costos_partidas existe y la columna no)
SET @sql = (
  SELECT IF(
    (SELECT COUNT(*) FROM information_schema.tables
      WHERE table_schema = DATABASE() AND table_name = 'costos_partidas') = 0,
    'DO 0',
    IF(
      (SELECT COUNT(*) FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = 'costos_partidas' AND column_name = 'ajuste') = 0,
      'ALTER TABLE costos_partidas ADD COLUMN ajuste DECIMAL(16,2) NOT NULL DEFAULT 0',
      'DO 0'
    )
  )
);
PREPARE stmt_catalogo FROM @sql;
EXECUTE stmt_catalogo;
DEALLOCATE PREPARE stmt_catalogo;

-- 2) El enum de origen debe aceptar 'conceptos'
SET @sql = (
  SELECT IF(
    (SELECT COUNT(*) FROM information_schema.columns
      WHERE table_schema = DATABASE() AND table_name = 'costos_partidas' AND column_name = 'origen') = 0,
    'DO 0',
    IF(
      (SELECT column_type FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = 'costos_partidas' AND column_name = 'origen') LIKE '%conceptos%',
      'DO 0',
      'ALTER TABLE costos_partidas MODIFY COLUMN origen ENUM(''manual'',''cantidades'',''conceptos'') NOT NULL DEFAULT ''manual'''
    )
  )
);
PREPARE stmt_catalogo FROM @sql;
EXECUTE stmt_catalogo;
DEALLOCATE PREPARE stmt_catalogo;

-- 3) Catálogo de conceptos
CREATE TABLE IF NOT EXISTS conceptos_obra (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    partida_id INT NULL,                             -- NULL = concepto general (sin partida)
    clave VARCHAR(60) NULL,                          -- única por proyecto; NULL = sin clave
    descripcion VARCHAR(255) NOT NULL,
    unidad VARCHAR(30) NOT NULL DEFAULT '',
    cantidad DECIMAL(14,3) NOT NULL DEFAULT 0,       -- cantidad contractual
    precio_unitario DECIMAL(14,2) NOT NULL DEFAULT 0,
    origen VARCHAR(255) NOT NULL DEFAULT 'manual',
    notas VARCHAR(500) NOT NULL DEFAULT '',
    activo TINYINT(1) NOT NULL DEFAULT 1,
    orden INT NOT NULL DEFAULT 0,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_co_clave (proyecto_id, clave),
    KEY idx_co_partida (proyecto_id, partida_id, orden),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (partida_id) REFERENCES partidas(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 4) Reparto del concepto entre actividades
CREATE TABLE IF NOT EXISTS actividad_conceptos (
    actividad_id INT NOT NULL,
    concepto_id INT NOT NULL,
    cantidad DECIMAL(14,3) NOT NULL DEFAULT 0,
    motivo_excedido VARCHAR(500) NOT NULL DEFAULT '',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (actividad_id, concepto_id),
    KEY idx_ac_concepto (concepto_id),
    FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE CASCADE,
    FOREIGN KEY (concepto_id) REFERENCES conceptos_obra(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 5) Cargos del proyecto
CREATE TABLE IF NOT EXISTS proyecto_cargos (
    proyecto_id INT NOT NULL PRIMARY KEY,
    indirectos DECIMAL(6,3) NOT NULL DEFAULT 0,
    financiamiento DECIMAL(6,3) NOT NULL DEFAULT 0,
    utilidad DECIMAL(6,3) NOT NULL DEFAULT 0,
    iva DECIMAL(6,3) NOT NULL DEFAULT 16,
    iva_en_bac TINYINT(1) NOT NULL DEFAULT 0,
    actualizado TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 6) Si una versión anterior dejó las tablas sin alguna columna imprescindible, se agrega
SET @sql = (
  SELECT IF(
    (SELECT COUNT(*) FROM information_schema.columns
      WHERE table_schema = DATABASE() AND table_name = 'actividad_conceptos' AND column_name = 'motivo_excedido') = 0,
    'ALTER TABLE actividad_conceptos ADD COLUMN motivo_excedido VARCHAR(500) NOT NULL DEFAULT ""',
    'DO 0'
  )
);
PREPARE stmt_catalogo FROM @sql;
EXECUTE stmt_catalogo;
DEALLOCATE PREPARE stmt_catalogo;

SELECT 'Catálogo de conceptos: esquema listo' AS resultado;
