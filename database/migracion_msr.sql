-- MSR: materiales, submittals y reporte de estatus
ALTER TABLE proyectos ADD COLUMN msr_umbral_critico INT NOT NULL DEFAULT 60 AFTER numero_trabajo;

CREATE TABLE IF NOT EXISTS materiales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    referencia VARCHAR(100) NOT NULL DEFAULT '',
    nombre VARCHAR(255) NOT NULL,
    especialidad VARCHAR(100) NOT NULL DEFAULT '',
    link_web VARCHAR(500) DEFAULT NULL,
    imagen VARCHAR(100) DEFAULT NULL,
    ficha_tecnica VARCHAR(200) DEFAULT NULL,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS submittals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    fecha DATE NOT NULL,
    numero_trabajo VARCHAR(50) NOT NULL DEFAULT '',
    departamento VARCHAR(100) NOT NULL DEFAULT '',
    para VARCHAR(150) NOT NULL DEFAULT '',
    numero_submittal VARCHAR(100) NOT NULL,
    especialidad VARCHAR(100) NOT NULL DEFAULT '',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS submittal_materiales (
    submittal_id INT NOT NULL,
    material_id INT NOT NULL,
    PRIMARY KEY (submittal_id, material_id),
    FOREIGN KEY (submittal_id) REFERENCES submittals(id) ON DELETE CASCADE,
    FOREIGN KEY (material_id) REFERENCES materiales(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS msr_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    submittal_id INT NOT NULL,
    material_id INT NOT NULL,
    seccion VARCHAR(150) NOT NULL DEFAULT '',
    fecha_envio_requerida DATE DEFAULT NULL,
    no_submittal_contratista VARCHAR(100) NOT NULL DEFAULT '',
    fecha_envio_real DATE DEFAULT NULL,
    fecha_respuesta DATE DEFAULT NULL,
    status ENUM('A','P','RA') NOT NULL DEFAULT 'P',
    fecha_compra_prog DATE DEFAULT NULL,
    fecha_instalacion_prog DATE DEFAULT NULL,
    fecha_compra_real DATE DEFAULT NULL,
    fecha_entrega_prog DATE DEFAULT NULL,
    fecha_entrega_real DATE DEFAULT NULL,
    proveedor VARCHAR(200) NOT NULL DEFAULT '',
    observaciones TEXT,
    orden INT NOT NULL DEFAULT 0,
    UNIQUE KEY uq_sub_mat (submittal_id, material_id),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (submittal_id) REFERENCES submittals(id) ON DELETE CASCADE,
    FOREIGN KEY (material_id) REFERENCES materiales(id) ON DELETE CASCADE
) ENGINE=InnoDB;
