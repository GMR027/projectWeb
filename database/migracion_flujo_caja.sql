-- ============================================================
-- Flujo de caja y costo final
-- Fechas de cobro de estimaciones, de pago de compromisos y gastos recurrentes.
-- ============================================================

--   mysql -u root project < database/migracion_flujo_caja.sql

ALTER TABLE costos_contrato
    ADD COLUMN dias_cobro INT NOT NULL DEFAULT 30 AFTER iva_pct;

ALTER TABLE estimaciones
    ADD COLUMN fecha_cobro_estimada DATE NULL AFTER movimiento_id,
    ADD COLUMN fecha_cobro_real DATE NULL AFTER fecha_cobro_estimada;

ALTER TABLE compromisos
    ADD COLUMN fecha_pago_programada DATE NULL AFTER entrega;

CREATE TABLE IF NOT EXISTS flujo_conceptos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    nombre VARCHAR(200) NOT NULL DEFAULT '',
    tipo ENUM('ingreso','egreso') NOT NULL DEFAULT 'egreso',
    monto DECIMAL(16,2) NOT NULL DEFAULT 0,
    frecuencia ENUM('semanal','quincenal','mensual') NOT NULL DEFAULT 'mensual',
    fecha_inicio DATE NOT NULL,
    fecha_fin DATE NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    notas VARCHAR(500) NOT NULL DEFAULT '',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_flujo_proyecto (proyecto_id, activo),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;
