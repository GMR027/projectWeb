-- ============================================================
-- Estimaciones completas
-- Subtotal por partida + IVA − retención − amortización del anticipo ± ajuste.
-- Al aprobarse descuentan el saldo del contrato y crean su movimiento en Costos.
-- ============================================================

--   mysql -u root project < database/migracion_estimaciones.sql

CREATE TABLE IF NOT EXISTS costos_contrato (
    proyecto_id INT NOT NULL PRIMARY KEY,
    monto_contrato DECIMAL(16,2) NOT NULL DEFAULT 0,
    anticipo_monto DECIMAL(16,2) NOT NULL DEFAULT 0,
    retencion_pct DECIMAL(5,2) NOT NULL DEFAULT 5,
    iva_pct DECIMAL(5,2) NOT NULL DEFAULT 16,
    notas TEXT,
    actualizado TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS estimaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    numero VARCHAR(40) NOT NULL,
    fecha DATE NOT NULL,
    periodo_desde DATE NULL,
    periodo_hasta DATE NULL,
    subtotal DECIMAL(16,2) NOT NULL DEFAULT 0,
    iva_pct DECIMAL(5,2) NOT NULL DEFAULT 16,
    iva DECIMAL(16,2) NOT NULL DEFAULT 0,
    retencion_pct DECIMAL(5,2) NOT NULL DEFAULT 5,
    retencion DECIMAL(16,2) NOT NULL DEFAULT 0,
    anticipo_pct DECIMAL(5,2) NOT NULL DEFAULT 0,
    amortizacion DECIMAL(16,2) NOT NULL DEFAULT 0,
    ajuste DECIMAL(16,2) NOT NULL DEFAULT 0,
    total_bruto DECIMAL(16,2) NOT NULL DEFAULT 0,
    neto DECIMAL(16,2) NOT NULL DEFAULT 0,
    estado ENUM('borrador','aprobada','pagada') NOT NULL DEFAULT 'borrador',
    movimiento_id INT NULL,
    notas TEXT,
    usuario VARCHAR(150) NOT NULL DEFAULT '',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_est_proyecto (proyecto_id, estado, fecha),
    KEY idx_est_numero (proyecto_id, numero),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (movimiento_id) REFERENCES costos_movimientos(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS estimacion_partidas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    estimacion_id INT NOT NULL,
    partida_id INT NOT NULL,
    importe DECIMAL(16,2) NOT NULL DEFAULT 0,
    KEY idx_ep_estimacion (estimacion_id),
    KEY idx_ep_partida (partida_id),
    FOREIGN KEY (estimacion_id) REFERENCES estimaciones(id) ON DELETE CASCADE,
    FOREIGN KEY (partida_id) REFERENCES partidas(id) ON DELETE CASCADE
) ENGINE=InnoDB;
