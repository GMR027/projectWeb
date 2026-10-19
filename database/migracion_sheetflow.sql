-- SheetFlow: tablas (hojas de cálculo ligeras) del proyecto, reutilizables en vPlan, Editor y Redactor
CREATE TABLE IF NOT EXISTS sheetflow_hojas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    fecha DATE NOT NULL,
    departamento VARCHAR(100) NOT NULL DEFAULT '',
    descripcion VARCHAR(300) NOT NULL DEFAULT '',
    datos LONGTEXT,                                   -- JSON saneado (Classes\Sheetflow::limpiar): cols, rows, calc, tot, estilo
    creado_por INT DEFAULT NULL,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_proyecto (proyecto_id, departamento),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Tabla de vPlan creada desde SheetFlow (permite «Actualizar desde SheetFlow»); si se borra la hoja solo se pierde el vínculo
ALTER TABLE vplan_tablas ADD COLUMN sheetflow_id INT NULL;
ALTER TABLE vplan_tablas ADD CONSTRAINT fk_vplan_tabla_sheetflow FOREIGN KEY (sheetflow_id) REFERENCES sheetflow_hojas(id) ON DELETE SET NULL;
