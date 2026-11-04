-- ============================================================
-- Dinero comprometido
-- Órdenes de compra, subcontratos y servicios que reservan presupuesto
-- desde que se emiten, aunque la factura llegue después.
-- Al aplicar una factura, el monto pasa de comprometido a costo real.
-- ============================================================

--   mysql -u root project < database/migracion_compromisos.sql

CREATE TABLE IF NOT EXISTS compromisos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    partida_id INT NOT NULL,
    actividad_id INT NULL,
    tipo ENUM('orden_compra','subcontrato','servicio') NOT NULL DEFAULT 'orden_compra',
    estado ENUM('borrador','vigente','parcial','cerrado','cancelado') NOT NULL DEFAULT 'vigente',
    proveedor VARCHAR(200) NOT NULL DEFAULT '',
    numero VARCHAR(80) NOT NULL DEFAULT '',
    concepto VARCHAR(255) NOT NULL DEFAULT '',
    monto DECIMAL(16,2) NOT NULL DEFAULT 0,
    fecha DATE NOT NULL,
    entrega DATE NULL,
    archivo_id INT NULL,
    notas TEXT,
    motivo_sobregiro VARCHAR(500) NOT NULL DEFAULT '',
    usuario VARCHAR(150) NOT NULL DEFAULT '',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_comp_proyecto (proyecto_id, estado),
    KEY idx_comp_partida (partida_id),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (partida_id) REFERENCES partidas(id) ON DELETE CASCADE,
    FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE SET NULL,
    FOREIGN KEY (archivo_id) REFERENCES archivos(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS compromiso_aplicaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    compromiso_id INT NOT NULL,
    movimiento_id INT NULL,
    monto DECIMAL(16,2) NOT NULL DEFAULT 0,
    fecha DATE NOT NULL,
    factura VARCHAR(100) NOT NULL DEFAULT '',
    usuario VARCHAR(150) NOT NULL DEFAULT '',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ca_compromiso (compromiso_id, id),
    KEY idx_ca_movimiento (movimiento_id),
    FOREIGN KEY (compromiso_id) REFERENCES compromisos(id) ON DELETE CASCADE,
    FOREIGN KEY (movimiento_id) REFERENCES costos_movimientos(id) ON DELETE SET NULL
) ENGINE=InnoDB;
