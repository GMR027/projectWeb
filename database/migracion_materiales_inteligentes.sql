-- Materiales inteligentes: con la opción activa, la app de escritorio verifica cada material contra la normativa y especificaciones cargadas
-- (normativa_fragmentos) y guarda el resultado en el material. La web solo lo muestra.
CREATE TABLE IF NOT EXISTS materiales_inteligentes (
    proyecto_id INT NOT NULL PRIMARY KEY,
    activo TINYINT(1) NOT NULL DEFAULT 0,
    actualizado TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS material_verificaciones (
    material_id INT NOT NULL PRIMARY KEY,
    proyecto_id INT NOT NULL,
    estado ENUM('aprobado','no_aprobado','revisar','sin_normativa') NOT NULL,
    huella CHAR(32) NOT NULL,                        -- datos del material al verificar; si cambian, la verificación queda desactualizada
    resumen VARCHAR(500) NOT NULL DEFAULT '',
    info MEDIUMTEXT NULL,                            -- JSON: motivos[] y requisitos[] {documento, clausula, pagina, requisito, cumple}
    modelo VARCHAR(150) NOT NULL DEFAULT '',
    verificado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_proyecto (proyecto_id, estado),
    FOREIGN KEY (material_id) REFERENCES materiales(id) ON DELETE CASCADE,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;
