-- ============================================================
-- Registro de riesgos de la obra
-- Lista manual por proyecto: probabilidad (1-3) × impacto (1-3) = severidad (1-9).
-- La severidad no se guarda: se calcula al consultar (Classes\Riesgos).
-- Un riesgo puede ligarse a una actividad (opcional) y convertirse en restricción
-- cuando se materializa.
-- ============================================================

--   mysql -u root project < database/migracion_riesgos.sql

CREATE TABLE IF NOT EXISTS riesgos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    actividad_id INT NULL,
    titulo VARCHAR(250) NOT NULL,
    descripcion TEXT,
    categoria ENUM('tecnico','clima','suministro','seguridad','financiero','permisos','mano_obra','calidad','otro') NOT NULL DEFAULT 'tecnico',
    probabilidad TINYINT NOT NULL DEFAULT 2,   -- 1 baja · 2 media · 3 alta
    impacto TINYINT NOT NULL DEFAULT 2,        -- 1 bajo · 2 medio · 3 alto
    responsable VARCHAR(150) NOT NULL DEFAULT '',
    estrategia ENUM('mitigar','transferir','aceptar','evitar') NOT NULL DEFAULT 'mitigar',
    plan_accion TEXT,
    fecha_compromiso DATE NULL,
    estado ENUM('identificado','en_tratamiento','materializado','cerrado') NOT NULL DEFAULT 'identificado',
    restriccion_id INT NULL,                  -- restricción creada al materializarse
    creado_por VARCHAR(150) NOT NULL DEFAULT '',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_riesgos_proyecto (proyecto_id, estado, fecha_compromiso),
    KEY idx_riesgos_actividad (actividad_id),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE SET NULL
) ENGINE=InnoDB;
