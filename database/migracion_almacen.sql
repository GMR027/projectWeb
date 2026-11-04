-- ============================================================
-- Órdenes de compra y almacén
-- La orden de compra (compromiso) lleva materiales y cantidades.
-- Las recepciones entran al almacén (parciales) y las salidas se cargan
-- a una actividad. La existencia sale de los movimientos, no se escribe a mano.
-- ============================================================

--   mysql -u root project < database/migracion_almacen.sql

ALTER TABLE materiales
    ADD COLUMN stock_minimo DECIMAL(14,3) NOT NULL DEFAULT 0 AFTER cantidad;

CREATE TABLE IF NOT EXISTS almacenes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    nombre VARCHAR(120) NOT NULL DEFAULT 'Almacén principal',
    ubicacion VARCHAR(200) NOT NULL DEFAULT '',
    principal TINYINT(1) NOT NULL DEFAULT 1,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_alm_proyecto (proyecto_id, activo),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS compra_lineas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    compromiso_id INT NOT NULL,
    material_id INT NOT NULL,
    cantidad DECIMAL(14,3) NOT NULL DEFAULT 0,
    precio_unitario DECIMAL(14,2) NOT NULL DEFAULT 0,
    importe DECIMAL(16,2) NOT NULL DEFAULT 0,
    recibido DECIMAL(14,3) NOT NULL DEFAULT 0,
    KEY idx_cl_compromiso (compromiso_id),
    KEY idx_cl_material (material_id),
    FOREIGN KEY (compromiso_id) REFERENCES compromisos(id) ON DELETE CASCADE,
    FOREIGN KEY (material_id) REFERENCES materiales(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS recepciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    compromiso_id INT NULL,
    almacen_id INT NOT NULL,
    fecha DATE NOT NULL,
    factura VARCHAR(100) NOT NULL DEFAULT '',
    proveedor VARCHAR(200) NOT NULL DEFAULT '',
    notas TEXT,
    usuario VARCHAR(150) NOT NULL DEFAULT '',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_rec_proyecto (proyecto_id, fecha),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (compromiso_id) REFERENCES compromisos(id) ON DELETE SET NULL,
    FOREIGN KEY (almacen_id) REFERENCES almacenes(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS salidas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    almacen_id INT NOT NULL,
    actividad_id INT NOT NULL,
    fecha DATE NOT NULL,
    recibio VARCHAR(150) NOT NULL DEFAULT '',
    notas TEXT,
    usuario VARCHAR(150) NOT NULL DEFAULT '',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_sal_proyecto (proyecto_id, fecha),
    KEY idx_sal_actividad (actividad_id),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (almacen_id) REFERENCES almacenes(id) ON DELETE CASCADE,
    FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS almacen_movimientos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    almacen_id INT NOT NULL,
    material_id INT NOT NULL,
    tipo ENUM('entrada','salida','ajuste_entrada','ajuste_salida') NOT NULL,
    cantidad DECIMAL(14,3) NOT NULL DEFAULT 0,
    costo_unitario DECIMAL(14,2) NOT NULL DEFAULT 0,
    importe DECIMAL(16,2) NOT NULL DEFAULT 0,
    fecha DATE NOT NULL,
    actividad_id INT NULL,
    compromiso_id INT NULL,
    recepcion_id INT NULL,
    salida_id INT NULL,
    motivo VARCHAR(300) NOT NULL DEFAULT '',
    usuario VARCHAR(150) NOT NULL DEFAULT '',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_am_proyecto (proyecto_id, material_id, fecha),
    KEY idx_am_almacen (almacen_id, material_id),
    KEY idx_am_actividad (actividad_id),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (almacen_id) REFERENCES almacenes(id) ON DELETE CASCADE,
    FOREIGN KEY (material_id) REFERENCES materiales(id) ON DELETE CASCADE,
    FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE SET NULL,
    FOREIGN KEY (compromiso_id) REFERENCES compromisos(id) ON DELETE SET NULL,
    FOREIGN KEY (recepcion_id) REFERENCES recepciones(id) ON DELETE CASCADE,
    FOREIGN KEY (salida_id) REFERENCES salidas(id) ON DELETE CASCADE
) ENGINE=InnoDB;
