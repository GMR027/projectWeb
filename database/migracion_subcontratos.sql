-- ============================================================
-- Subcontratos
-- Alcance, monto, retención, anticipo, documentos obligatorios y
-- estimaciones propias. Sin documentos vigentes no se autoriza el pago.
-- ============================================================

--   mysql -u root project < database/migracion_subcontratos.sql

CREATE TABLE IF NOT EXISTS subcontratos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    partida_id INT NOT NULL,
    compromiso_id INT NULL,
    codigo VARCHAR(40) NOT NULL DEFAULT '',
    proveedor VARCHAR(200) NOT NULL DEFAULT '',
    alcance TEXT,
    monto DECIMAL(16,2) NOT NULL DEFAULT 0,
    retencion_pct DECIMAL(5,2) NOT NULL DEFAULT 10,
    anticipo_pct DECIMAL(5,2) NOT NULL DEFAULT 0,
    fecha_inicio DATE NULL,
    fecha_fin DATE NULL,
    estado ENUM('borrador','vigente','cerrado','cancelado') NOT NULL DEFAULT 'vigente',
    notas TEXT,
    usuario VARCHAR(150) NOT NULL DEFAULT '',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_sub_proyecto (proyecto_id, estado),
    KEY idx_sub_partida (partida_id),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (partida_id) REFERENCES partidas(id) ON DELETE CASCADE,
    FOREIGN KEY (compromiso_id) REFERENCES compromisos(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS subcontrato_documentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subcontrato_id INT NOT NULL,
    tipo ENUM('fianza','seguro','permisos','alta_personal','otro') NOT NULL,
    archivo_id INT NULL,
    vencimiento DATE NULL,
    validado TINYINT(1) NOT NULL DEFAULT 0,
    validado_por VARCHAR(150) NOT NULL DEFAULT '',
    validado_en DATETIME NULL,
    notas VARCHAR(500) NOT NULL DEFAULT '',
    actualizado TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_sub_doc (subcontrato_id, tipo),
    FOREIGN KEY (subcontrato_id) REFERENCES subcontratos(id) ON DELETE CASCADE,
    FOREIGN KEY (archivo_id) REFERENCES archivos(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS subcontrato_estimaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subcontrato_id INT NOT NULL,
    proyecto_id INT NOT NULL,
    numero VARCHAR(40) NOT NULL DEFAULT '',
    fecha DATE NOT NULL,
    periodo_desde DATE NULL,
    periodo_hasta DATE NULL,
    subtotal DECIMAL(16,2) NOT NULL DEFAULT 0,
    iva_pct DECIMAL(5,2) NOT NULL DEFAULT 16,
    iva DECIMAL(16,2) NOT NULL DEFAULT 0,
    retencion_pct DECIMAL(5,2) NOT NULL DEFAULT 0,
    retencion DECIMAL(16,2) NOT NULL DEFAULT 0,
    anticipo_pct DECIMAL(5,2) NOT NULL DEFAULT 0,
    amortizacion DECIMAL(16,2) NOT NULL DEFAULT 0,
    ajuste DECIMAL(16,2) NOT NULL DEFAULT 0,
    total_bruto DECIMAL(16,2) NOT NULL DEFAULT 0,
    neto DECIMAL(16,2) NOT NULL DEFAULT 0,
    estado ENUM('borrador','aprobada','pagada') NOT NULL DEFAULT 'borrador',
    movimiento_id INT NULL,
    motivo_autorizacion VARCHAR(500) NOT NULL DEFAULT '',
    autorizado_por VARCHAR(150) NOT NULL DEFAULT '',
    notas TEXT,
    usuario VARCHAR(150) NOT NULL DEFAULT '',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_sube_sub (subcontrato_id, estado, fecha),
    FOREIGN KEY (subcontrato_id) REFERENCES subcontratos(id) ON DELETE CASCADE,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (movimiento_id) REFERENCES costos_movimientos(id) ON DELETE SET NULL
) ENGINE=InnoDB;
