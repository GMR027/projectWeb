-- ============================================================
-- Base de datos: project
-- Sistema de Programas de Obra
-- ============================================================

CREATE DATABASE IF NOT EXISTS project CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE project;

-- ============================================================
-- PLANES COMERCIALES
-- ============================================================
CREATE TABLE planes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    clave VARCHAR(30) NOT NULL UNIQUE,
    nombre VARCHAR(80) NOT NULL,
    precio_mensual DECIMAL(10,2) NOT NULL DEFAULT 0,
    precio_anual DECIMAL(10,2) NOT NULL DEFAULT 0,
    limite_obras INT NULL,                    -- NULL = ilimitado
    usuarios_edicion INT NULL,                -- incluye al propietario; NULL = ilimitado
    limite_visitantes INT NULL,               -- NULL = ilimitado
    almacenamiento_gb INT NULL,               -- NULL = sin tope total (legacy)
    almacenamiento_por_obra_mb INT NOT NULL DEFAULT 300,
    permite_gestores TINYINT(1) NOT NULL DEFAULT 0,
    features_json TEXT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    orden SMALLINT NOT NULL DEFAULT 0
) ENGINE=InnoDB;

-- ============================================================
-- USUARIOS Y AUTENTICACIÓN
-- ============================================================
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    rol ENUM('admin', 'cliente', 'visitante') NOT NULL DEFAULT 'visitante',
    nivel_acceso ENUM('ver', 'editar') NOT NULL DEFAULT 'ver',   -- visitante de solo lectura o colaborador que edita
    logo VARCHAR(100) DEFAULT NULL,
    organizacion_id INT NULL,   -- organización a la que pertenece (NULL en admin/visitantes externos)
    rol_org ENUM('propietario', 'gestor', 'colaborador', 'visitante') NOT NULL DEFAULT 'visitante',
    puede_crear_proyectos TINYINT(1) NOT NULL DEFAULT 0,   -- gestor autorizado a crear obras
    limite_proyectos INT NOT NULL DEFAULT 1,   -- legacy: proyectos que puede crear un cliente
    limite_almacenamiento_mb INT NOT NULL DEFAULT 300,   -- legacy: MB por proyecto de un cliente
    creado_por INT DEFAULT NULL,   -- cliente que creó a este visitante/colaborador
    limite_visitantes INT NOT NULL DEFAULT 2,        -- legacy: cuántos visitantes puede crear un cliente
    limite_colaboradores INT NOT NULL DEFAULT 3,     -- legacy: cuántos colaboradores puede crear un cliente
    herramientas TEXT NULL,        -- visitante: claves de las herramientas que ve (JSON); NULL = todas
    herramientas_edicion TEXT NULL,   -- colaborador: claves de las que además puede editar (JSON)
    puede_archivos_ajenos TINYINT(1) NOT NULL DEFAULT 0,   -- colaborador: editar archivos subidos por el dueño
    token VARCHAR(100) DEFAULT NULL,
    confirmado TINYINT(1) DEFAULT 0,
    debe_cambiar_password TINYINT(1) NOT NULL DEFAULT 0,   -- contraseña inicial (seed o temporal): se pide cambiarla al entrar
    ultimo_acceso DATETIME NULL,                        -- último acceso correcto
    novedades_vistas DATETIME NULL,   -- última vez que abrió las novedades de la plataforma
    inicio_json TEXT NULL,            -- Inicio personalizado (bloques y orden), Fase 4.5
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_usuarios_org (organizacion_id),
    FOREIGN KEY (creado_por) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- ORGANIZACIONES (empresa que contrata el plan)
-- ============================================================
CREATE TABLE organizaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(200) NOT NULL,
    razon_social VARCHAR(200) NULL,
    rfc VARCHAR(20) NULL,
    correo_facturacion VARCHAR(150) NULL,
    telefono VARCHAR(30) NULL,
    logo VARCHAR(100) NULL,
    propietario_id INT NULL,                  -- usuario propietario (uno por organización)
    plan_id VARCHAR(30) NOT NULL DEFAULT 'obra',
    estado_plan ENUM('trial','activo','suspendido','cancelado') NOT NULL DEFAULT 'trial',
    trial_hasta DATE NULL,
    plan_inicio DATE NULL,
    plan_fin DATE NULL,
    gracia_hasta DATE NULL,
    precio_legado DECIMAL(10,2) NULL,
    limites_json TEXT NULL,                   -- overrides de límites (legacy o acuerdos)
    almacenamiento_bytes BIGINT NOT NULL DEFAULT 0,
    marca_activa TINYINT(1) NOT NULL DEFAULT 0,
    marca_nombre VARCHAR(150) NULL,
    marca_logo VARCHAR(100) NULL,
    marca_color VARCHAR(7) NULL,
    marca_color2 VARCHAR(7) NULL,
    archivada TINYINT(1) NOT NULL DEFAULT 0,
    archivada_en DATETIME NULL,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_org_propietario (propietario_id),
    KEY idx_org_plan (plan_id),
    KEY idx_org_archivada (archivada)
) ENGINE=InnoDB;

-- Pagos manuales de la organización (facturación sin pasarela)
CREATE TABLE organizacion_pagos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    organizacion_id INT NOT NULL,
    fecha DATE NOT NULL,
    monto DECIMAL(12,2) NOT NULL DEFAULT 0,
    periodo_inicio DATE NULL,
    periodo_fin DATE NULL,
    metodo VARCHAR(40) NOT NULL DEFAULT '',
    referencia VARCHAR(100) NOT NULL DEFAULT '',
    cfdi_uuid VARCHAR(50) NOT NULL DEFAULT '',
    notas VARCHAR(255) NOT NULL DEFAULT '',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_pagos_org (organizacion_id, fecha),
    FOREIGN KEY (organizacion_id) REFERENCES organizaciones(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Intentos de acceso fallidos (protección contra fuerza bruta): 5 fallos por IP + correo bloquean 15 minutos
CREATE TABLE login_intentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip VARCHAR(45) NOT NULL,
    email VARCHAR(100) NOT NULL DEFAULT '',
    intentos INT NOT NULL DEFAULT 0,
    ultimo INT NOT NULL DEFAULT 0,             -- unix timestamp del último intento
    bloqueado_hasta INT NOT NULL DEFAULT 0,    -- unix timestamp hasta el que no se permite intentar
    UNIQUE KEY uq_login_ip_email (ip, email),
    KEY idx_login_ultimo (ultimo)
) ENGINE=InnoDB;

-- ============================================================
-- PROYECTOS
-- ============================================================
CREATE TABLE proyectos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(50) NOT NULL,
    nombre VARCHAR(200) NOT NULL,
    descripcion TEXT,
    ubicacion VARCHAR(200),
    empresa VARCHAR(200) DEFAULT NULL,
    contratista VARCHAR(200) DEFAULT NULL,
    contratista_logo VARCHAR(100) DEFAULT NULL,
    numero_trabajo VARCHAR(50) DEFAULT NULL,
    msr_umbral_critico INT NOT NULL DEFAULT 60,
    logo VARCHAR(100) DEFAULT NULL,
    caratula VARCHAR(100) DEFAULT NULL,
    fecha_inicio DATE NOT NULL,
    fecha_fin DATE NOT NULL,
    color VARCHAR(7) DEFAULT '#FF5A1F',
    usuario_id INT NOT NULL,
    modo_dependencias ENUM('apagado','informativo','automatico') NOT NULL DEFAULT 'apagado',
    modo_dependencias_materiales ENUM('apagado','informativo','automatico') NOT NULL DEFAULT 'apagado',
    mostrar_subactividades TINYINT(1) NOT NULL DEFAULT 0,   -- programa y vistas de planeación (Ajustes → General)
    registros_por_pagina SMALLINT NOT NULL DEFAULT 10,
    tema_modo ENUM('usuario','claro','oscuro') NOT NULL DEFAULT 'usuario',
    tema_paleta VARCHAR(20) NOT NULL DEFAULT 'naranja',
    tema_textura VARCHAR(20) NOT NULL DEFAULT 'ninguna',   -- textura de fondo (Ajustes → Apariencia)
    tema_textura_intensidad TINYINT UNSIGNED NOT NULL DEFAULT 35,   -- 0-100
    tema_halo_color VARCHAR(7) NOT NULL DEFAULT '',   -- vacío = color de acento de la paleta
    tema_halo_intensidad TINYINT UNSIGNED NOT NULL DEFAULT 45,   -- 0-100
    tema_halo_expansion SMALLINT UNSIGNED NOT NULL DEFAULT 100,   -- 50-250 (%)
    nombres_herramientas TINYINT(1) NOT NULL DEFAULT 0,   -- barra superior de herramientas: nombre bajo cada icono (Ajustes → Apariencia)
    menu_superior ENUM('clasico','panel','pestanas') NOT NULL DEFAULT 'clasico',   -- barra superior: clásico, panel fijo o pestañas por sección
    ayuda_burbujas TINYINT(1) NOT NULL DEFAULT 1,   -- mensajes emergentes de ayuda de la interfaz (Ajustes → Apariencia)
    clima_auto TINYINT(1) NOT NULL DEFAULT 0,     -- bitácora de clima automática + aviso del pronóstico (Telegram)
    clima_ultimo DATE NULL,
    clima_aviso DATE NULL,
    resumen_matutino TINYINT(1) NOT NULL DEFAULT 0,   -- resumen de la obra por la mañana (Telegram, evento resumen.matutino)
    resumen_ultimo DATE NULL,
    programa_nombre VARCHAR(200) NULL,
    programa_columnas_json TEXT NULL,
    programa_indicador_json TEXT NULL,
    marca_activa TINYINT(1) NOT NULL DEFAULT 0,   -- marca del cliente en los PDF (Fase 4.4)
    marca_nombre VARCHAR(150) NULL,
    marca_logo VARCHAR(100) NULL,
    marca_color VARCHAR(7) NULL,
    marca_color2 VARCHAR(7) NULL,
    limite_almacenamiento_mb INT NULL,   -- MB asignados por el admin a este proyecto (NULL = hereda del cliente o 300 MB)
    organizacion_id INT NULL,             -- organización dueña del proyecto
    archivado TINYINT(1) NOT NULL DEFAULT 0,   -- archivar libera cupo de obras activas, no almacenamiento
    archivado_en DATETIME NULL,
    archivado_por INT NULL,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_proyectos_org (organizacion_id, archivado),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- PARTIDAS (contenedores de actividades)
-- ============================================================
CREATE TABLE partidas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    codigo VARCHAR(50) NOT NULL,
    nombre VARCHAR(200) NOT NULL,
    descripcion TEXT,
    color_bg VARCHAR(7) DEFAULT NULL,
    orden INT DEFAULT 0,
    fecha_inicio DATE DEFAULT NULL,
    fecha_fin DATE DEFAULT NULL,
    cerrada TINYINT(1) NOT NULL DEFAULT 0,
    cerrada_por VARCHAR(150) NOT NULL DEFAULT '',
    cerrada_en DATETIME NULL,
    cierre_motivo VARCHAR(500) NOT NULL DEFAULT '',
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    UNIQUE KEY uq_partida_codigo (proyecto_id, codigo)
) ENGINE=InnoDB;

-- ============================================================
-- ACTIVIDADES
-- ============================================================
CREATE TABLE actividades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    partida_id INT NOT NULL,
    calendario_id INT NULL,
    frente_id INT NULL,
    codigo VARCHAR(50) NOT NULL,
    nombre VARCHAR(200) NOT NULL,
    descripcion TEXT,
    fecha_inicio DATE NOT NULL,
    duracion_dias INT NOT NULL DEFAULT 1,
    fecha_fin DATE NOT NULL,
    fecha_inicio_real DATE NULL,
    fecha_fin_real DATE NULL,
    duracion_restante DECIMAL(6,2) NULL,
    restriccion_tipo ENUM('ninguna','no_antes','no_despues','terminar_antes') NOT NULL DEFAULT 'ninguna',
    restriccion_fecha DATE NULL,
    restriccion_motivo VARCHAR(500) NOT NULL DEFAULT '',
    progreso TINYINT UNSIGNED DEFAULT 0,
    prioridad ENUM('baja', 'media', 'alta', 'critica') DEFAULT 'media',
    status ENUM('no_iniciada', 'en_proceso', 'completada') DEFAULT 'no_iniciada',
    pta TINYINT(1) NOT NULL DEFAULT 1, -- 4WLA: 1 = OK, 0 = pendiente (!)
    hito TINYINT(1) NOT NULL DEFAULT 0, -- 1 = hito (duración 0, inicio = fin)
    sin_material TINYINT(1) NOT NULL DEFAULT 0, -- 1 = la actividad no requiere material
    fecha_fija TINYINT(1) NOT NULL DEFAULT 0,   -- 1 = el modo automático de dependencias no la mueve
    responsable VARCHAR(150) NULL,
    rec_inicio DATE NULL,                       -- fechas de recuperación de la actividad
    rec_fin DATE NULL,
    libre_texto VARCHAR(255) NULL,              -- campos libres (su nombre está en proyectos.programa_columnas_json)
    libre_numero DECIMAL(14,4) NULL,
    libre_fecha DATE NULL,
    icono VARCHAR(120) NULL,                       -- imagen pequeña de la actividad (public/build/img/actividades-iconos/)
    orden INT DEFAULT 0,
    FOREIGN KEY (partida_id) REFERENCES partidas(id) ON DELETE CASCADE,
    UNIQUE KEY uq_actividad_codigo (partida_id, codigo),
    KEY idx_actividad_orden (partida_id, orden)
) ENGINE=InnoDB;

-- ============================================================
-- RESTRICCIONES
-- ============================================================
CREATE TABLE tipos_restriccion (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL UNIQUE,
    color VARCHAR(7) DEFAULT '#FF5A1F'
) ENGINE=InnoDB;

INSERT INTO tipos_restriccion (nombre, color) VALUES
('Clima', '#4CD6FB'),
('Contrato', '#FFB59E'),
('Ingeniería', '#B2C8E8'),
('RFI', '#FF5A1F'),
('Submittal', '#00B2D6'),
('Material', '#F59E0B'),
('Equipo', '#8B5CF6'),
('Mano de Obra', '#10B981'),
('Acceso', '#EF4444'),
('Otro', '#6B7280');

CREATE TABLE restricciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    actividad_id INT NOT NULL,
    subactividad_id INT NULL,                     -- si la restricción es propia de una subactividad
    tipo_id INT NOT NULL,
    codigo VARCHAR(50) DEFAULT NULL,
    descripcion TEXT,
    fecha DATE DEFAULT NULL,
    estado ENUM('activa', 'resuelta', 'cancelada') DEFAULT 'activa',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE CASCADE,
    FOREIGN KEY (tipo_id) REFERENCES tipos_restriccion(id),
    KEY idx_restriccion_estado (actividad_id, estado),
    KEY idx_restriccion_subactividad (subactividad_id)
) ENGINE=InnoDB;

-- ============================================================
-- SUBACTIVIDADES (avance de una actividad por partes)
-- ============================================================
CREATE TABLE subactividades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    actividad_id INT NOT NULL,
    calendario_id INT NULL,
    frente_id INT NULL,
    nombre VARCHAR(200) NOT NULL,
    descripcion TEXT NULL,
    fecha_inicio DATE NOT NULL,
    fecha_fin DATE NOT NULL,
    fecha_inicio_real DATE NULL,
    fecha_fin_real DATE NULL,
    duracion_restante DECIMAL(6,2) NULL,
    restriccion_tipo ENUM('ninguna','no_antes','no_despues','terminar_antes') NOT NULL DEFAULT 'ninguna',
    restriccion_fecha DATE NULL,
    restriccion_motivo VARCHAR(500) NOT NULL DEFAULT '',
    progreso TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'avance 0-100',
    status ENUM('no_iniciada','en_proceso','completada') NOT NULL DEFAULT 'no_iniciada',
    prioridad ENUM('baja','media','alta','critica') NULL DEFAULT 'media',
    pta TINYINT(1) NOT NULL DEFAULT 1,
    hito TINYINT NOT NULL DEFAULT 0,
    sin_material TINYINT(1) NOT NULL DEFAULT 0,
    orden INT NULL DEFAULT 0,
    fecha_fija TINYINT(1) NOT NULL DEFAULT 0,
    responsable VARCHAR(150) NULL,
    rec_inicio DATE NULL,
    rec_fin DATE NULL,
    libre_texto VARCHAR(255) NULL,
    libre_numero DECIMAL(14,4) NULL,
    libre_fecha DATE NULL,
    icono VARCHAR(120) NULL,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE CASCADE,
    KEY idx_subactividades_actividad (actividad_id),
    KEY idx_subactividades_actividad_orden (actividad_id, orden)
) ENGINE=InnoDB;

-- Restricciones propias de una subactividad (la FK se agrega aquí porque subactividades se crea después)
ALTER TABLE restricciones
    ADD CONSTRAINT fk_restriccion_subactividad FOREIGN KEY (subactividad_id) REFERENCES subactividades(id) ON DELETE CASCADE;

-- ============================================================
-- CALENDARIO OPERATIVO (por proyecto)
-- ============================================================
CREATE TABLE calendario_jornada (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    dia_semana TINYINT NOT NULL COMMENT '1=Lunes ... 7=Domingo',
    factor DECIMAL(3,2) NOT NULL DEFAULT 1.00 COMMENT '0=inhabil, 0.5=medio turno, 1=completo',
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    UNIQUE KEY uq_jornada (proyecto_id, dia_semana)
) ENGINE=InnoDB;

CREATE TABLE calendario_festivos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    fecha DATE NOT NULL,
    motivo VARCHAR(200) NOT NULL,
    tipo ENUM('festivo', 'paro_tecnico', 'clima', 'otro') DEFAULT 'festivo',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    UNIQUE KEY uq_festivo (proyecto_id, fecha)
) ENGINE=InnoDB;

-- ============================================================
-- EXPORTACIONES (historial)
-- ============================================================
CREATE TABLE exportaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    usuario_id INT NOT NULL,
    formato ENUM('pdf', 'xml', 'pod') NOT NULL,
    nombre_archivo VARCHAR(255) NOT NULL,
    hash_sha256 VARCHAR(64) NOT NULL,
    ruta VARCHAR(255) NOT NULL,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB;

-- Proyectos asignados por el admin a clientes/visitantes
CREATE TABLE IF NOT EXISTS proyecto_accesos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    usuario_id INT NOT NULL,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    UNIQUE KEY uq_acceso (proyecto_id, usuario_id)
) ENGINE=InnoDB;

-- ============================================================
-- DATOS SEMILLA
-- ============================================================
-- Usuario admin por defecto (password: admin123 - CAMBIAR EN PRODUCCIÓN; se pide cambiarla al entrar)
INSERT INTO usuarios (nombre, email, password, rol, confirmado, debe_cambiar_password) VALUES
('Administrador', 'admin@po.local', '$2y$12$n6xOV.ytNTY7dysHh66yeuxncU33gwjWbEWX2q6T9XIRYiqzoXX72', 'admin', 1, 1);

-- Planes comerciales (la app también los define en Classes\Plan; esta tabla permite ajustarlos sin desplegar)
INSERT INTO planes (clave, nombre, precio_mensual, precio_anual, limite_obras, usuarios_edicion, limite_visitantes, almacenamiento_gb, almacenamiento_por_obra_mb, permite_gestores, features_json, activo, orden) VALUES
('obra', 'Obra', 390, 3900, 2, 2, NULL, 5, 2048, 0, '[]', 1, 10),
('pro', 'Pro', 990, 9900, 8, 6, NULL, 30, 6144, 1, '["gestores","ia","api"]', 1, 20),
('constructora', 'Constructora', 2490, 24900, 25, 15, NULL, 100, 10240, 1, '["gestores","ia","api","sso","marca"]', 1, 30),
('corporativo', 'Corporativo', 5900, 59000, NULL, NULL, NULL, 300, 25600, 1, '["gestores","ia","api","sso","marca","multiempresa"]', 1, 40),
('legacy', 'Legacy', 0, 0, NULL, NULL, NULL, NULL, 300, 0, '["ia","api","sso","marca","multiempresa"]', 1, 90);

-- Organización interna de ProjectWeb (admin, demo y proyectos de ejemplo)
INSERT INTO organizaciones (nombre, plan_id, estado_plan, propietario_id, plan_inicio)
SELECT 'ProjectWeb', 'legacy', 'activo', id, CURDATE() FROM usuarios WHERE email = 'admin@po.local' LIMIT 1;
UPDATE usuarios SET organizacion_id = (SELECT id FROM organizaciones WHERE nombre = 'ProjectWeb' LIMIT 1),
    rol_org = 'propietario', puede_crear_proyectos = 1
WHERE email = 'admin@po.local';

-- ============================================================
-- MSR (materiales, submittals, reporte de estatus)
-- ============================================================
CREATE TABLE especialidades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    UNIQUE KEY uq_esp (proyecto_id, nombre),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE materiales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    referencia VARCHAR(100) NOT NULL DEFAULT '',
    nombre_base VARCHAR(255) DEFAULT NULL,       -- nombre + marca + modelo + valor extra = nombre
    marca VARCHAR(100) NOT NULL DEFAULT '',
    modelo VARCHAR(100) NOT NULL DEFAULT '',
    valor_extra VARCHAR(150) NOT NULL DEFAULT '',
    nombre VARCHAR(255) NOT NULL,                -- nombre completo armado
    especialidad VARCHAR(100) NOT NULL DEFAULT '',
    link_web VARCHAR(500) DEFAULT NULL,
    imagen VARCHAR(100) DEFAULT NULL,
    ficha_tecnica VARCHAR(200) DEFAULT NULL,
    certificado_calidad VARCHAR(255) DEFAULT NULL,
    cantidad DECIMAL(12,2) DEFAULT NULL,
    stock_minimo DECIMAL(14,3) NOT NULL DEFAULT 0,
    costo_unitario DECIMAL(14,2) DEFAULT NULL,
    unidad VARCHAR(30) NOT NULL DEFAULT '',
    compra ENUM('pendiente','comprado') NOT NULL DEFAULT 'pendiente',
    fecha_compra_prog DATE DEFAULT NULL,
    fecha_compra_real DATE DEFAULT NULL,
    fecha_entrega_prog DATE DEFAULT NULL,
    fecha_entrega_real DATE DEFAULT NULL,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Normas, certificaciones y especificaciones por material (búsqueda ampliada de normativa)
CREATE TABLE IF NOT EXISTS material_atributos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    material_id INT NOT NULL,
    tipo ENUM('norma','certificacion','especificacion') NOT NULL DEFAULT 'norma',
    codigo VARCHAR(80) NOT NULL DEFAULT '',
    titulo VARCHAR(200) NOT NULL DEFAULT '',
    valor VARCHAR(200) NOT NULL DEFAULT '',
    unidad VARCHAR(30) NOT NULL DEFAULT '',
    emisor VARCHAR(120) NOT NULL DEFAULT '',
    fuente_url VARCHAR(500) NOT NULL DEFAULT '',
    notas VARCHAR(500) NOT NULL DEFAULT '',
    origen ENUM('manual','ia','documento') NOT NULL DEFAULT 'manual',
    estado ENUM('propuesta','aceptada','rechazada') NOT NULL DEFAULT 'aceptada',
    creado_por VARCHAR(150) NOT NULL DEFAULT '',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_atributo (material_id, tipo, codigo, titulo),
    KEY idx_material (proyecto_id, material_id, estado),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (material_id) REFERENCES materiales(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Materiales que usa cada actividad (Planificador)
CREATE TABLE actividad_materiales (
    actividad_id INT NOT NULL,
    material_id INT NOT NULL,
    rol ENUM('instala','consume') NOT NULL DEFAULT 'instala',   -- instala: la actividad lo coloca; consume: solo lo usa
    PRIMARY KEY (actividad_id, material_id),
    FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE CASCADE,
    FOREIGN KEY (material_id) REFERENCES materiales(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE subactividad_materiales (
    subactividad_id INT NOT NULL,
    material_id INT NOT NULL,
    rol ENUM('instala','consume') NOT NULL DEFAULT 'instala',
    PRIMARY KEY (subactividad_id, material_id),
    FOREIGN KEY (subactividad_id) REFERENCES subactividades(id) ON DELETE CASCADE,
    FOREIGN KEY (material_id) REFERENCES materiales(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE msrs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    descripcion VARCHAR(500) NOT NULL DEFAULT '',
    columnas_json TEXT NULL,
    encabezado_id INT NULL,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE msr_partidas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    msr_id INT NOT NULL,
    titulo VARCHAR(150) NOT NULL,
    color VARCHAR(7) NOT NULL DEFAULT '',
    orden INT NOT NULL DEFAULT 0,
    FOREIGN KEY (msr_id) REFERENCES msrs(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE submittals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    msr_id INT DEFAULT NULL,
    fecha DATE NOT NULL,
    numero_trabajo VARCHAR(50) NOT NULL DEFAULT '',
    departamento VARCHAR(100) NOT NULL DEFAULT '',
    para VARCHAR(150) NOT NULL DEFAULT '',
    numero_submittal VARCHAR(100) NOT NULL,
    especialidad VARCHAR(100) NOT NULL DEFAULT '',
    estatus ENUM('enviado','rechazado','aprobado','aprobado_an') NOT NULL DEFAULT 'enviado',
    fecha_respuesta DATE DEFAULT NULL,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (msr_id) REFERENCES msrs(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE submittal_materiales (
    submittal_id INT NOT NULL,
    material_id INT NOT NULL,
    PRIMARY KEY (submittal_id, material_id),
    FOREIGN KEY (submittal_id) REFERENCES submittals(id) ON DELETE CASCADE,
    FOREIGN KEY (material_id) REFERENCES materiales(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE msr_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    msr_id INT DEFAULT NULL,
    submittal_id INT DEFAULT NULL,
    material_id INT NOT NULL,
    seccion VARCHAR(150) NOT NULL DEFAULT '',
    fecha_envio_requerida DATE DEFAULT NULL,
    no_submittal_contratista VARCHAR(100) NOT NULL DEFAULT '',
    fecha_envio_real DATE DEFAULT NULL,
    fecha_respuesta DATE DEFAULT NULL,
    status ENUM('A','AN','P','RA') NOT NULL DEFAULT 'P',
    fecha_compra_prog DATE DEFAULT NULL,
    fecha_instalacion_prog DATE DEFAULT NULL,
    fecha_compra_real DATE DEFAULT NULL,
    fecha_entrega_prog DATE DEFAULT NULL,
    fecha_entrega_real DATE DEFAULT NULL,
    proveedor VARCHAR(200) NOT NULL DEFAULT '',
    observaciones TEXT,
    extras_json TEXT NULL,
    orden INT NOT NULL DEFAULT 0,
    UNIQUE KEY uq_sub_mat (submittal_id, material_id),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (submittal_id) REFERENCES submittals(id) ON DELETE CASCADE,
    FOREIGN KEY (material_id) REFERENCES materiales(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- CURVAS DE PRODUCTIVIDAD
-- ============================================================
CREATE TABLE curvas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    nombre VARCHAR(200) NOT NULL,
    material_id INT DEFAULT NULL,
    cantidad INT NOT NULL DEFAULT 0,
    unidad VARCHAR(20) NOT NULL DEFAULT 'm',
    tipo_curva ENUM('lineal','final','inicio','campana') NOT NULL DEFAULT 'campana',
    fecha_inicio DATE NOT NULL,
    fecha_fin DATE NOT NULL,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (material_id) REFERENCES materiales(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE curva_reales (
    curva_id INT NOT NULL,
    periodo INT NOT NULL,
    valor DECIMAL(14,2) DEFAULT NULL,
    PRIMARY KEY (curva_id, periodo),
    FOREIGN KEY (curva_id) REFERENCES curvas(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- VPLAN (láminas de plan visual y tablas adicionales)
-- ============================================================
-- Un vPlan (paquete) agrupa las láminas (una por área) de un proyecto
CREATE TABLE vplan_paquetes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    nombre VARCHAR(200) NOT NULL,
    orden INT NOT NULL DEFAULT 0,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE vplan_laminas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    paquete_id INT DEFAULT NULL,
    partida_id INT DEFAULT NULL,
    titulo VARCHAR(200) NOT NULL,
    supervisor VARCHAR(200) NOT NULL DEFAULT '',
    ancho_mm SMALLINT UNSIGNED NOT NULL DEFAULT 432,   -- tamaño del lienzo (por defecto tabloide horizontal)
    alto_mm SMALLINT UNSIGNED NOT NULL DEFAULT 279,
    preset VARCHAR(20) NOT NULL DEFAULT 'tabloide',
    recovery_cols TINYINT NOT NULL DEFAULT 0,   -- en desuso: máscara anterior a recovery_n
    recovery_n TINYINT NOT NULL DEFAULT 0,      -- cuántas columnas de Recovery tiene la lámina
    restr_auto TINYINT NOT NULL DEFAULT 0,
    plano VARCHAR(100) DEFAULT NULL,
    simbologia VARCHAR(100) DEFAULT NULL,
    simbologia_grafico_id INT DEFAULT NULL,   -- simbología del schedule (gráfico del Editor); NULL = tabla estándar
    marcas_json LONGTEXT,
    marcas_png VARCHAR(100) DEFAULT NULL,
    marcas_estados_hash VARCHAR(64) DEFAULT NULL,
    materiales_tabla_id INT DEFAULT NULL,      -- tabla de instalación de materiales de la lámina (ver migracion_materiales_vplan.sql)
    guias_json TEXT,                          -- guías del modo diseño [{id,eje,pos,visible,bloqueada,nombre}]
    orden INT NOT NULL DEFAULT 0,
    visible TINYINT NOT NULL DEFAULT 1,       -- 0 = oculta (no sale en listas ni en el PDF)
    grupo VARCHAR(100) NOT NULL DEFAULT '',   -- disciplina, nivel…: agrupa las láminas en el gestor
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (paquete_id) REFERENCES vplan_paquetes(id) ON DELETE CASCADE,
    FOREIGN KEY (partida_id) REFERENCES partidas(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Bloques posicionables de la lámina (x, y, w, h en % de la hoja)
CREATE TABLE vplan_bloques (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lamina_id INT NOT NULL,
    tipo VARCHAR(20) NOT NULL,
    x DECIMAL(7,3) NOT NULL DEFAULT 0,
    y DECIMAL(7,3) NOT NULL DEFAULT 0,
    w DECIMAL(7,3) NOT NULL DEFAULT 20,
    h DECIMAL(7,3) NOT NULL DEFAULT 10,
    z INT NOT NULL DEFAULT 0,
    config_json TEXT,
    FOREIGN KEY (lamina_id) REFERENCES vplan_laminas(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE vplan_recovery (
    lamina_id INT NOT NULL,
    actividad_id INT NOT NULL,
    r1_inicio DATE DEFAULT NULL,
    r1_fin DATE DEFAULT NULL,
    r2_inicio DATE DEFAULT NULL,
    r2_fin DATE DEFAULT NULL,
    PRIMARY KEY (lamina_id, actividad_id),
    FOREIGN KEY (lamina_id) REFERENCES vplan_laminas(id) ON DELETE CASCADE,
    FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE vplan_recovery_n (
    lamina_id INT NOT NULL,
    actividad_id INT NOT NULL,
    n TINYINT NOT NULL,
    inicio DATE DEFAULT NULL,
    fin DATE DEFAULT NULL,
    PRIMARY KEY (lamina_id, actividad_id, n),
    FOREIGN KEY (lamina_id) REFERENCES vplan_laminas(id) ON DELETE CASCADE,
    FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE vplan_col_texto (
    lamina_id INT NOT NULL,
    actividad_id INT NOT NULL,
    col_id VARCHAR(12) NOT NULL,
    texto VARCHAR(500) NOT NULL DEFAULT '',
    PRIMARY KEY (lamina_id, actividad_id, col_id),
    FOREIGN KEY (lamina_id) REFERENCES vplan_laminas(id) ON DELETE CASCADE,
    FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE vplan_tablas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    titulo VARCHAR(200) NOT NULL,
    nombre_interno VARCHAR(150) DEFAULT NULL,   -- nombre en el catálogo (el título es el que se imprime)
    tipo ENUM('punch','restricciones','secundarias','otro','punchlist','materiales') NOT NULL DEFAULT 'punchlist',
    columnas_json TEXT,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE vplan_tabla_filas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tabla_id INT NOT NULL,
    orden INT NOT NULL DEFAULT 0,
    datos LONGTEXT,
    FOREIGN KEY (tabla_id) REFERENCES vplan_tablas(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE vplan_lamina_tablas (
    lamina_id INT NOT NULL,
    tabla_id INT NOT NULL,
    orden INT NOT NULL DEFAULT 0,
    PRIMARY KEY (lamina_id, tabla_id),
    FOREIGN KEY (lamina_id) REFERENCES vplan_laminas(id) ON DELETE CASCADE,
    FOREIGN KEY (tabla_id) REFERENCES vplan_tablas(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Actividades que muestra cada lámina (sin filas = toda la partida)
CREATE TABLE vplan_lamina_actividades (
    lamina_id INT NOT NULL,
    actividad_id INT NOT NULL,
    PRIMARY KEY (lamina_id, actividad_id),
    FOREIGN KEY (lamina_id) REFERENCES vplan_laminas(id) ON DELETE CASCADE,
    FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Notas de cada lámina
CREATE TABLE vplan_notas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lamina_id INT NOT NULL,
    descripcion TEXT NOT NULL,
    fecha DATETIME NOT NULL,
    FOREIGN KEY (lamina_id) REFERENCES vplan_laminas(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Editor de gráficos (ver database/migracion_editor_graficos.sql)
-- Categorías de gráficos (ver database/migracion_editor_categorias.sql)
CREATE TABLE editor_categorias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE editor_graficos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    tipo VARCHAR(12) NOT NULL DEFAULT 'grafico',   -- 'grafico' o 'simbologia' (ver database/migracion_simbologias.sql)
    categoria_id INT DEFAULT NULL,
    ancho INT NOT NULL DEFAULT 1920,
    alto INT NOT NULL DEFAULT 1080,
    fondo VARCHAR(30) NOT NULL DEFAULT 'liso',
    fondo_color VARCHAR(7) NOT NULL DEFAULT '#ffffff',
    fondo_imagen VARCHAR(100) DEFAULT NULL,   -- fondo personalizado (imagen propia)
    fondo_config TEXT,                        -- ajustes del fondo personalizado (JSON)
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (categoria_id) REFERENCES editor_categorias(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Láminas de cada gráfico (ver database/migracion_editor_laminas.sql)
CREATE TABLE editor_laminas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    grafico_id INT NOT NULL,
    orden INT NOT NULL DEFAULT 1,
    nombre VARCHAR(60) NOT NULL DEFAULT '',
    marcas_json LONGTEXT,
    marcas_png VARCHAR(100) DEFAULT NULL,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (grafico_id) REFERENCES editor_graficos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Láminas del Editor (/graficos) adjuntas a un paquete de submittal (ver database/migracion_submittal_laminas.sql)
CREATE TABLE submittal_laminas (
    submittal_id INT NOT NULL,
    lamina_id INT NOT NULL,
    orden INT NOT NULL DEFAULT 0,
    PRIMARY KEY (submittal_id, lamina_id),
    FOREIGN KEY (submittal_id) REFERENCES submittals(id) ON DELETE CASCADE,
    FOREIGN KEY (lamina_id) REFERENCES editor_laminas(id) ON DELETE CASCADE
) ENGINE=InnoDB;

ALTER TABLE vplan_laminas ADD CONSTRAINT fk_lamina_simbologia FOREIGN KEY (simbologia_grafico_id) REFERENCES editor_graficos(id) ON DELETE SET NULL;

-- Biblioteca de símbolos por proyecto (ver database/migracion_simbolos.sql)
CREATE TABLE simbolos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    categoria VARCHAR(60) NOT NULL DEFAULT '',
    archivo VARCHAR(100) NOT NULL,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- View360: gráficos interactivos con vistas 360 (ver database/migracion_view360.sql)
CREATE TABLE view360_graficos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    fecha DATE NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    version VARCHAR(30) NOT NULL DEFAULT '',
    grafico_id INT DEFAULT NULL,   -- gráfico del Editor que sirve de lienzo (ver database/migracion_view360_fondo.sql)
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (grafico_id) REFERENCES editor_graficos(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Notas de cada gráfico de View360 (ver database/migracion_view360_notas.sql)
CREATE TABLE view360_notas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    view360_id INT NOT NULL,
    descripcion TEXT NOT NULL,
    fecha DATETIME NOT NULL,
    FOREIGN KEY (view360_id) REFERENCES view360_graficos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Reporte diario y bitácora (ver database/migracion_reportes_bitacora.sql)

CREATE TABLE personal (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    departamento VARCHAR(100) NOT NULL DEFAULT '',
    tarifa_hora DECIMAL(12,2) NOT NULL DEFAULT 0,   -- costo por hora para el parte diario
    imagen VARCHAR(100) DEFAULT NULL,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE reportes_diarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    fecha DATE NOT NULL,
    area VARCHAR(150) NOT NULL DEFAULT '',
    turno ENUM('dia','noche') NOT NULL DEFAULT 'dia',
    supervisor VARCHAR(150) NOT NULL DEFAULT '',
    comentarios TEXT,
    estado ENUM('borrador','cerrado') NOT NULL DEFAULT 'borrador',
    clima_precip_mm DECIMAL(6,1) NULL,
    clima_viento_kmh DECIMAL(5,1) NULL,
    clima_tmax DECIMAL(4,1) NULL,
    clima_tmin DECIMAL(4,1) NULL,
    visitas VARCHAR(500) NOT NULL DEFAULT '',
    firma_nombre VARCHAR(150) NOT NULL DEFAULT '',
    firma_imagen VARCHAR(255) NULL,
    offline_uuid VARCHAR(64) NULL,
    creado_por VARCHAR(150) NOT NULL DEFAULT '',
    cerrado_en DATETIME NULL,
    personal_json LONGTEXT,      -- [id de personal, ...]
    actividades_json LONGTEXT,   -- [id de actividad del programa, ...]
    fotos_json LONGTEXT,         -- [nombre de archivo, ...]
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    KEY idx_reporte_proyecto_fecha (proyecto_id, fecha),
    UNIQUE KEY uq_reporte_offline (offline_uuid)
) ENGINE=InnoDB;

CREATE TABLE bitacora (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    fecha DATE NOT NULL,
    reporte_id INT NULL,
    departamento VARCHAR(100) NOT NULL DEFAULT '',
    descripcion TEXT NOT NULL,
    importancia ENUM('baja','media','alta','critica') NOT NULL DEFAULT 'media',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (reporte_id) REFERENCES reportes_diarios(id) ON DELETE SET NULL,
    KEY idx_bitacora_proyecto_fecha (proyecto_id, fecha)
) ENGINE=InnoDB;

CREATE TABLE bitacora_departamentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_bit_depto (proyecto_id, nombre),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Sección Archivo: categorías y archivos subidos por proyecto
CREATE TABLE archivo_categorias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_arch_cat (proyecto_id, nombre),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE archivo_carpetas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_arch_carpeta (proyecto_id, nombre),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE archivos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    subido_por INT DEFAULT NULL,   -- usuario que subió el archivo (NULL = anterior / del dueño)
    categoria_id INT DEFAULT NULL,
    carpeta_id INT DEFAULT NULL,
    fecha DATE NOT NULL,
    nombre VARCHAR(200) NOT NULL,
    numero_revision VARCHAR(30) NOT NULL DEFAULT '',
    archivo_original VARCHAR(255) NOT NULL,   -- nombre con el que se subió (se usa al descargar)
    archivo_guardado VARCHAR(100) NOT NULL,   -- nombre interno en storage/archivos/{proyecto}/
    tamano INT UNSIGNED NOT NULL DEFAULT 0,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (categoria_id) REFERENCES archivo_categorias(id) ON DELETE SET NULL,
    FOREIGN KEY (carpeta_id) REFERENCES archivo_carpetas(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Minutas de junta y personal del contratista
CREATE TABLE personal_contratista (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    puesto VARCHAR(100) NOT NULL DEFAULT '',
    empresa VARCHAR(150) NOT NULL DEFAULT '',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE minutas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    fecha DATE NOT NULL,
    nombre VARCHAR(200) NOT NULL,
    importancia ENUM('baja','media','alta','critica') NOT NULL DEFAULT 'media',
    departamento VARCHAR(100) NOT NULL DEFAULT '',   -- carpeta de la minuta en su herramienta
    contratista_json LONGTEXT,   -- [id de personal_contratista, ...]
    personal_json LONGTEXT,      -- [id de personal del proyecto, ...]
    temas_json LONGTEXT,         -- [{"tema","responsable","departamento"}, ...]
    compromisos_json LONGTEXT,   -- [{"tema","responsable","departamento"}, ...] (opcional)
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;
-- Mensajes de contacto (formulario público de la página principal) y de soporte (clientes). Llegan a la bandeja del admin.
CREATE TABLE IF NOT EXISTS contactos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    empresa VARCHAR(150) NOT NULL DEFAULT '',
    puesto VARCHAR(100) NOT NULL DEFAULT '',
    correo VARCHAR(150) NOT NULL,
    telefono VARCHAR(40) NOT NULL DEFAULT '',
    plan VARCHAR(40) NOT NULL DEFAULT '',
    proyectos_aprox VARCHAR(40) NOT NULL DEFAULT '',
    preferencia VARCHAR(20) NOT NULL DEFAULT 'correo',
    origen VARCHAR(60) NOT NULL DEFAULT '',
    mensaje TEXT NOT NULL,
    ip VARCHAR(45) NOT NULL DEFAULT '',
    leido TINYINT(1) NOT NULL DEFAULT 0,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS soportes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    tema VARCHAR(200) NOT NULL,
    informacion TEXT NOT NULL,
    leido TINYINT(1) NOT NULL DEFAULT 0,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS soporte_respuestas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    soporte_id INT NOT NULL,
    titulo VARCHAR(200) NOT NULL,
    descripcion TEXT NOT NULL,
    fecha DATETIME NOT NULL,
    visto TINYINT(1) NOT NULL DEFAULT 0,   -- 0 = el cliente aún no la ha visto
    FOREIGN KEY (soporte_id) REFERENCES soportes(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS soporte_respuesta_fotos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    respuesta_id INT NOT NULL,
    archivo VARCHAR(100) NOT NULL,
    FOREIGN KEY (respuesta_id) REFERENCES soporte_respuestas(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Foto diaria del espacio usado en los volúmenes (archivos y base de datos) para estimar cuándo se llenan
CREATE TABLE IF NOT EXISTS almacenamiento_historial (
    fecha DATE PRIMARY KEY,
    archivos_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
    bd_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB;

-- Cajetines reutilizables por proyecto (ver database/migracion_cajetines.sql)
CREATE TABLE cajetines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    orientacion ENUM('vertical','banda') NOT NULL DEFAULT 'vertical',
    ancho TINYINT UNSIGNED NOT NULL DEFAULT 28,
    filas_json TEXT,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Estampas inteligentes (ver database/migracion_estampas.sql)
CREATE TABLE estampas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    config_json LONGTEXT,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_proyecto (proyecto_id),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Evidencias fotográficas por actividad (ver database/migracion_evidencias.sql)
CREATE TABLE evidencias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    actividad_id INT DEFAULT NULL,
    fecha DATE NOT NULL,
    descripcion VARCHAR(300) NOT NULL DEFAULT '',
    foto VARCHAR(100) NOT NULL,
    departamento VARCHAR(100) NOT NULL DEFAULT '',
    categoria VARCHAR(100) NOT NULL DEFAULT '',
    usuario_id INT DEFAULT NULL,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE SET NULL,
    KEY idx_evidencia_proyecto_fecha (proyecto_id, fecha)
) ENGINE=InnoDB;

-- Curva S (ver database/migracion_curva_s.sql)
CREATE TABLE actividad_avance (
    actividad_id INT NOT NULL,
    fecha DATE NOT NULL,
    progreso TINYINT UNSIGNED NOT NULL,
    PRIMARY KEY (actividad_id, fecha),
    FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE curva_s_manual (
    proyecto_id INT NOT NULL,
    fecha DATE NOT NULL,
    avance DECIMAL(5,2) NOT NULL,
    PRIMARY KEY (proyecto_id, fecha),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Dependencias de obra (ver database/migracion_dependencias.sql; además proyectos.modo_dependencias y actividades.fecha_fija)
CREATE TABLE actividad_dependencias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    predecesora_id INT NOT NULL,
    sucesora_id INT NOT NULL,
    tipo ENUM('FS','SS','FF','SF') NOT NULL DEFAULT 'FS',
    retraso INT NOT NULL DEFAULT 0,
    UNIQUE KEY uq_dependencia (predecesora_id, sucesora_id),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (predecesora_id) REFERENCES actividades(id) ON DELETE CASCADE,
    FOREIGN KEY (sucesora_id) REFERENCES actividades(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE dependencias_cambios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    cambios_json LONGTEXT NOT NULL,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Dependencias que tocan una subactividad (las actividad↔actividad siguen en actividad_dependencias)
CREATE TABLE subactividad_dependencias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    pred_tipo ENUM('actividad','subactividad') NOT NULL,
    pred_id INT NOT NULL,
    suc_tipo ENUM('actividad','subactividad') NOT NULL,
    suc_id INT NOT NULL,
    tipo ENUM('FS','SS','FF','SF') NOT NULL DEFAULT 'FS',
    retraso INT NOT NULL DEFAULT 0,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_subdependencia (pred_tipo, pred_id, suc_tipo, suc_id),
    KEY idx_subdependencia_proyecto (proyecto_id),
    KEY idx_subdependencia_pred (pred_tipo, pred_id),
    KEY idx_subdependencia_suc (suc_tipo, suc_id),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Dependencias entre materiales (ver database/migracion_dependencias_materiales.sql)
CREATE TABLE material_dependencias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    predecesor_id INT NOT NULL,
    sucesor_id INT NOT NULL,
    requisito ENUM('entrega','instalacion') NOT NULL DEFAULT 'entrega',
    retraso INT NOT NULL DEFAULT 0,
    nota VARCHAR(255) NOT NULL DEFAULT '',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_material_dependencia (proyecto_id, predecesor_id, sucesor_id),
    KEY idx_matdep_sucesor (proyecto_id, sucesor_id),
    KEY idx_matdep_predecesor (proyecto_id, predecesor_id),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (predecesor_id) REFERENCES materiales(id) ON DELETE CASCADE,
    FOREIGN KEY (sucesor_id) REFERENCES materiales(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE material_dependencias_cambios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    cambios_json LONGTEXT NOT NULL,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;
-- Conexiones de IA que el admin usa desde la app de escritorio (Project Web Desktop). No se guardan API keys.
CREATE TABLE IF NOT EXISTS herramientas_ia (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    tipo VARCHAR(20) NOT NULL,
    url VARCHAR(255) NOT NULL DEFAULT '',
    modelo VARCHAR(100) NOT NULL DEFAULT '',
    notas VARCHAR(255) NOT NULL DEFAULT '',
    activa TINYINT(1) NOT NULL DEFAULT 1,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Propuestas del Programador de actividades (IA). Son escenarios aparte: nunca modifican `actividades`.
CREATE TABLE IF NOT EXISTS programador_propuestas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    modelo VARCHAR(150) NOT NULL DEFAULT '',
    fin_actual DATE DEFAULT NULL,
    fin_propuesto DATE DEFAULT NULL,
    resumen_json LONGTEXT NOT NULL,   -- totales, obstáculos y advertencias
    filas_json LONGTEXT NOT NULL,     -- una fila por actividad: actual vs. propuesto + justificación
    guardada TINYINT(1) NOT NULL DEFAULT 0,   -- guardada por el admin: no se borra al rotar las últimas 10
    nombre VARCHAR(150) NOT NULL DEFAULT '',
    seguimiento_json LONGTEXT NULL,   -- estado/responsable de cada obstáculo y mejora
    aceptadas_json LONGTEXT NULL,     -- partidas de la propuesta ya aceptadas en el programa
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Estado previo a aceptar una partida de una propuesta (para deshacer)
CREATE TABLE IF NOT EXISTS programador_respaldos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    propuesta_id INT NOT NULL,
    partida_id INT NOT NULL,
    datos_json LONGTEXT NOT NULL,
    deshecho TINYINT(1) NOT NULL DEFAULT 0,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Inconsistencias de proyecto: análisis (corridas) guardables y sus hallazgos, cada uno con una solución que el admin acepta o rechaza.
CREATE TABLE IF NOT EXISTS inconsistencias_corridas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    nombre VARCHAR(150) NOT NULL DEFAULT '',
    guardada TINYINT(1) NOT NULL DEFAULT 0,       -- las no guardadas rotan (se conservan las 5 últimas)
    modelo VARCHAR(150) NOT NULL DEFAULT '',
    del_sistema INT NOT NULL DEFAULT 0,
    de_la_ia INT NOT NULL DEFAULT 0,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS inconsistencias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    corrida_id INT NOT NULL,
    huella CHAR(40) NOT NULL,                     -- identifica el hallazgo para no repetir lo ya resuelto o rechazado
    grupo VARCHAR(60) NOT NULL DEFAULT '',       -- problema (regla) para agrupar y reportar
    categoria VARCHAR(30) NOT NULL,
    severidad ENUM('baja','media','alta','critica') NOT NULL DEFAULT 'media',
    origen ENUM('sistema','ia') NOT NULL,
    titulo VARCHAR(200) NOT NULL,
    descripcion TEXT NOT NULL,
    evidencia_json TEXT NULL,                     -- de dónde sale el hallazgo: {fuente, cita, entidad_id}
    actividades_json TEXT NULL,                   -- ids de actividades implicadas
    solucion_json TEXT NOT NULL,                  -- {texto, accion, params, esperado, evidencia}
    estado ENUM('pendiente','aceptada','rechazada','obsoleta') NOT NULL DEFAULT 'pendiente',
    motivo_rechazo VARCHAR(60) NOT NULL DEFAULT '',-- por qué se rechazó (para medir precisión por regla)
    antes_json TEXT NULL,                         -- valores previos a aplicar la solución (para revertir)
    modelo VARCHAR(150) NOT NULL DEFAULT '',
    detectado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    vigente_hasta DATETIME NULL,                  -- pasado este momento el hallazgo deja de aplicarse
    resuelto_por VARCHAR(150) NOT NULL DEFAULT '',
    resuelto_en DATETIME NULL,
    UNIQUE KEY uq_corrida_huella (corrida_id, huella),
    KEY idx_estado (proyecto_id, estado),
    KEY idx_grupo (proyecto_id, grupo, estado),
    KEY idx_vigencia (proyecto_id, estado, vigente_hasta),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (corrida_id) REFERENCES inconsistencias_corridas(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Registro de cada lote que revisó la IA en un análisis de inconsistencias: actividades cubiertas, qué devolvió y si falló.
CREATE TABLE IF NOT EXISTS inconsistencias_lotes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    corrida_id INT NOT NULL,
    orden INT NOT NULL DEFAULT 0,
    titulo VARCHAR(150) NOT NULL,
    estado ENUM('ok','error') NOT NULL DEFAULT 'ok',
    error VARCHAR(500) NOT NULL DEFAULT '',
    actividades_json MEDIUMTEXT NULL,             -- [{id, codigo, nombre}] cubiertas por el lote (al momento del análisis)
    hallazgos_json MEDIUMTEXT NULL,               -- [{titulo, severidad, categoria, accion}] ya validados
    respuesta_json MEDIUMTEXT NULL,               -- respuesta cruda de la IA (recortada)
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_corrida (corrida_id, orden),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (corrida_id) REFERENCES inconsistencias_corridas(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Análisis de materiales: cada corrida (análisis guardable) agrupa hallazgos con una solución que el admin acepta o rechaza.
CREATE TABLE IF NOT EXISTS analisis_materiales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    nombre VARCHAR(150) NOT NULL DEFAULT '',
    guardada TINYINT(1) NOT NULL DEFAULT 0,       -- las no guardadas rotan (se conservan las 5 últimas)
    modelo VARCHAR(150) NOT NULL DEFAULT '',
    del_sistema INT NOT NULL DEFAULT 0,
    de_la_ia INT NOT NULL DEFAULT 0,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS analisis_materiales_hallazgos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    corrida_id INT NOT NULL,
    huella CHAR(40) NOT NULL,
    grupo VARCHAR(60) NOT NULL DEFAULT '',
    categoria VARCHAR(30) NOT NULL,
    severidad ENUM('baja','media','alta','critica') NOT NULL DEFAULT 'media',
    origen ENUM('sistema','ia') NOT NULL,
    titulo VARCHAR(255) NOT NULL,
    descripcion TEXT NOT NULL,
    evidencia_json TEXT NULL,                     -- de dónde sale el hallazgo: {fuente, cita, entidad_id}
    materiales_json TEXT NULL,                    -- ids de materiales implicados
    submittals_json TEXT NULL,                    -- ids de submittals implicados
    solucion_json TEXT NOT NULL,                  -- {texto, accion, params, esperado, evidencia}
    estado ENUM('pendiente','aceptada','rechazada','obsoleta') NOT NULL DEFAULT 'pendiente',
    motivo_rechazo VARCHAR(60) NOT NULL DEFAULT '',-- por qué se rechazó (para medir precisión por regla)
    antes_json TEXT NULL,                         -- valores previos a aplicar la solución (para revertir)
    modelo VARCHAR(150) NOT NULL DEFAULT '',
    detectado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    vigente_hasta DATETIME NULL,                  -- pasado este momento el hallazgo deja de aplicarse
    resuelto_por VARCHAR(150) NOT NULL DEFAULT '',
    resuelto_en DATETIME NULL,
    UNIQUE KEY uq_corrida_huella (corrida_id, huella),
    KEY idx_estado (proyecto_id, estado),
    KEY idx_grupo (proyecto_id, grupo, estado),
    KEY idx_vigencia (proyecto_id, estado, vigente_hasta),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (corrida_id) REFERENCES analisis_materiales(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Registro de cada lote que revisó la IA en un análisis: materiales cubiertos, qué devolvió y si falló (para decidir y actuar después).
CREATE TABLE IF NOT EXISTS analisis_materiales_lotes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    corrida_id INT NOT NULL,
    orden INT NOT NULL DEFAULT 0,
    titulo VARCHAR(150) NOT NULL,
    estado ENUM('ok','error') NOT NULL DEFAULT 'ok',
    error VARCHAR(500) NOT NULL DEFAULT '',
    materiales_json MEDIUMTEXT NULL,              -- [{id, nombre}] analizados en el lote (nombre al momento del análisis)
    hallazgos_json MEDIUMTEXT NULL,               -- [{titulo, severidad, categoria, accion, materiales}] ya validados
    respuesta_json MEDIUMTEXT NULL,               -- respuesta cruda de la IA (recortada)
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_corrida (corrida_id, orden),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (corrida_id) REFERENCES analisis_materiales(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Historial de cambios del proyecto: lo que hace la IA (Auditoria::registrar) y lo que se cambia a mano en las
-- herramientas (HistorialCambios::registrar). Solo se agregan filas: revertir, aceptar o reabrir crea otro evento.
-- origen 'ia' nace aceptado (nada cambia datos sin que el admin lo acepte); origen 'manual' nace pendiente de revisión.
CREATE TABLE IF NOT EXISTS auditoria_cambios (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    origen ENUM('ia','manual') NOT NULL DEFAULT 'ia',
    herramienta VARCHAR(30) NOT NULL,                 -- programador | inconsistencias | materiales | programa | ...
    evento ENUM('aplicado','revertido','rechazado','reabierto','creado','modificado','eliminado') NOT NULL,
    estado ENUM('pendiente','aceptado','revertido') NOT NULL DEFAULT 'pendiente',
    estado_en DATETIME NULL,
    estado_por VARCHAR(150) NOT NULL DEFAULT '',
    accion VARCHAR(40) NOT NULL,
    entidad_tipo VARCHAR(20) NOT NULL DEFAULT '',     -- actividad | partida | material | restriccion | dependencia | submittal | proyecto
    entidad_id INT NULL,
    entidad_nombre VARCHAR(255) NOT NULL DEFAULT '',
    resumen VARCHAR(500) NOT NULL DEFAULT '',
    antes_json MEDIUMTEXT NULL,
    despues_json MEDIUMTEXT NULL,
    referencia_id INT NULL,                           -- hallazgo, propuesta o corrida que originó el cambio
    modelo VARCHAR(150) NOT NULL DEFAULT '',
    usuario VARCHAR(150) NOT NULL DEFAULT '',
    usuario_id INT NULL,                              -- autor exacto (NULL en filas anteriores a la migración)
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_proyecto_fecha (proyecto_id, creado),
    KEY idx_auditoria_usuario (proyecto_id, usuario_id),
    KEY idx_proyecto_estado (proyecto_id, estado, creado),
    KEY idx_entidad (proyecto_id, entidad_tipo, entidad_id),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Informes generados por las herramientas de IA (reporte diario, resumen semanal, lookahead, minutas, cierre…). El contenido se calcula
-- en el servidor; la IA solo redacta. Un informe es un borrador hasta que el admin lo aprueba, y algunos tipos se pueden aplicar a su módulo.
CREATE TABLE IF NOT EXISTS informes_ia (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    tipo VARCHAR(30) NOT NULL,
    titulo VARCHAR(200) NOT NULL,
    parametros_json TEXT NULL,
    contenido_json LONGTEXT NOT NULL,             -- {secciones:[{titulo,tipo,...}], aplicar:{...}}
    origen ENUM('sistema','ia') NOT NULL DEFAULT 'sistema',
    modelo VARCHAR(150) NOT NULL DEFAULT '',
    estado ENUM('borrador','aprobado','descartado') NOT NULL DEFAULT 'borrador',
    aplicado_en DATETIME NULL,
    aplicado_ref VARCHAR(100) NOT NULL DEFAULT '',
    creado_por VARCHAR(150) NOT NULL DEFAULT '',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_proyecto_tipo (proyecto_id, tipo, creado),
    KEY idx_informes_borrador (proyecto_id, tipo, estado, aplicado_en),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Grabaciones de reuniones (minutas) guardadas en el proyecto. El audio vive en storage/grabaciones/<proyecto>/ (no en public) y cuenta para la cuota.
CREATE TABLE IF NOT EXISTS grabaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    nombre VARCHAR(200) NOT NULL DEFAULT '',
    archivo VARCHAR(100) NOT NULL,
    mime VARCHAR(60) NOT NULL DEFAULT '',
    tamano INT UNSIGNED NOT NULL DEFAULT 0,
    duracion_seg INT UNSIGNED NOT NULL DEFAULT 0,
    transcripcion LONGTEXT NULL,
    creado_por VARCHAR(150) NOT NULL DEFAULT '',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_proyecto (proyecto_id, creado),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Vigilancia continua de anomalías (Inconsistencias): última foto del proyecto para detectar lo que cambió desde la comprobación anterior.
CREATE TABLE IF NOT EXISTS vigilancia_estado (
    proyecto_id INT PRIMARY KEY,
    huellas_json LONGTEXT NOT NULL,        -- hallazgos del sistema vistos en la última comprobación
    metricas_json TEXT NOT NULL,           -- avance global, fin del programa y conteos
    nuevos_json LONGTEXT NULL,             -- lo nuevo (hallazgos y cambios bruscos) de la última comprobación
    revisado DATETIME NOT NULL,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Herramientas de IA, fase 2: niveles de riesgo por acción, vigilancia en el servidor y medidor de uso del modelo.
CREATE TABLE IF NOT EXISTS ia_ajustes (
    proyecto_id INT PRIMARY KEY,
    riesgo_json TEXT NULL,                            -- {accion: 'permiso'|'simple'}; vacío = valores por defecto
    vigilancia_servidor TINYINT(1) NOT NULL DEFAULT 0,
    vigilancia_minutos INT NOT NULL DEFAULT 60,
    vigilancia_ultima DATETIME NULL,
    vigilancia_origen VARCHAR(12) NOT NULL DEFAULT '',  -- cron | web
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ia_uso (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NULL,
    herramienta VARCHAR(60) NOT NULL,
    modelo VARCHAR(150) NOT NULL DEFAULT '',
    proveedor VARCHAR(30) NOT NULL DEFAULT '',
    duracion_ms INT UNSIGNED NOT NULL DEFAULT 0,
    caracteres_entrada INT UNSIGNED NOT NULL DEFAULT 0,
    caracteres_salida INT UNSIGNED NOT NULL DEFAULT 0,
    exito TINYINT(1) NOT NULL DEFAULT 1,
    usuario VARCHAR(150) NOT NULL DEFAULT '',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_proyecto_fecha (proyecto_id, creado),
    KEY idx_fecha (creado),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Normativa y especificaciones: PDFs (español o inglés) que se leen, se parten por cláusulas y se vinculan a materiales y actividades.
CREATE TABLE IF NOT EXISTS normativa_documentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    titulo VARCHAR(200) NOT NULL,
    tipo ENUM('norma','especificacion','otro') NOT NULL DEFAULT 'especificacion',
    codigo VARCHAR(80) NOT NULL DEFAULT '',          -- p. ej. NOM-001-SEDE, CSI 27 15 00
    idioma ENUM('es','en','mixto') NOT NULL DEFAULT 'es',
    archivo VARCHAR(100) NOT NULL,                   -- en storage/normativa/<proyecto>/
    tamano INT UNSIGNED NOT NULL DEFAULT 0,
    paginas INT UNSIGNED NOT NULL DEFAULT 0,
    fragmentos INT UNSIGNED NOT NULL DEFAULT 0,
    estado ENUM('indexado','sin_texto','error') NOT NULL DEFAULT 'indexado',
    nota VARCHAR(300) NOT NULL DEFAULT '',
    creado_por VARCHAR(150) NOT NULL DEFAULT '',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_proyecto (proyecto_id),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS normativa_fragmentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    documento_id INT NOT NULL,
    proyecto_id INT NOT NULL,
    pagina INT UNSIGNED NOT NULL DEFAULT 1,
    clausula VARCHAR(60) NOT NULL DEFAULT '',
    titulo VARCHAR(250) NOT NULL DEFAULT '',
    texto TEXT NOT NULL,
    idioma ENUM('es','en') NOT NULL DEFAULT 'es',
    csi CHAR(6) NULL,                                -- sección tipo CSI (27 15 00 → 271500) si el encabezado la trae
    KEY idx_doc (documento_id),
    KEY idx_csi (proyecto_id, csi),
    FULLTEXT KEY ft_texto (titulo, texto),
    FOREIGN KEY (documento_id) REFERENCES normativa_documentos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS normativa_aplicaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    documento_id INT NULL,                           -- manual: documento (opcional)
    fragmento_id INT NULL,                           -- cláusula concreta, si se conoce
    entidad_tipo ENUM('actividad','material') NOT NULL,
    entidad_id INT NOT NULL,
    referencia VARCHAR(200) NOT NULL DEFAULT '',     -- texto libre en vinculaciones manuales (p. ej. «NOM-001 art. 250»)
    requisito TEXT NULL,                             -- resumen del requisito (en español)
    nota VARCHAR(500) NOT NULL DEFAULT '',
    origen ENUM('manual','csi','palabras','ia') NOT NULL,
    estado ENUM('propuesta','aceptada','rechazada') NOT NULL DEFAULT 'propuesta',
    puntaje DECIMAL(5,2) NOT NULL DEFAULT 0,
    modelo VARCHAR(150) NOT NULL DEFAULT '',
    resuelto_por VARCHAR(150) NOT NULL DEFAULT '',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_entidad (proyecto_id, entidad_tipo, entidad_id),
    KEY idx_estado (proyecto_id, estado),
    UNIQUE KEY uq_vinculo (fragmento_id, entidad_tipo, entidad_id),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (documento_id) REFERENCES normativa_documentos(id) ON DELETE CASCADE,
    FOREIGN KEY (fragmento_id) REFERENCES normativa_fragmentos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

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

-- Redactor (ver database/migracion_redactor.sql)
CREATE TABLE IF NOT EXISTS redactor_documentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    fecha DATE NOT NULL,
    departamento VARCHAR(100) NOT NULL DEFAULT '',
    idioma VARCHAR(8) NOT NULL DEFAULT 'es',          -- es, en o es+en (diccionarios del corrector)
    papel VARCHAR(10) NOT NULL DEFAULT 'carta',       -- carta, a4, oficio, tabloide
    orientacion VARCHAR(10) NOT NULL DEFAULT 'vertical',
    margen_sup DECIMAL(4,2) NOT NULL DEFAULT 2.54,    -- cm
    margen_inf DECIMAL(4,2) NOT NULL DEFAULT 2.54,
    margen_izq DECIMAL(4,2) NOT NULL DEFAULT 2.54,
    margen_der DECIMAL(4,2) NOT NULL DEFAULT 2.54,
    numerar TINYINT(1) NOT NULL DEFAULT 1,            -- numerador de página
    encabezado TINYINT(1) NOT NULL DEFAULT 0,         -- encabezado con nombre, departamento y fecha
    rastrear TINYINT(1) NOT NULL DEFAULT 0,           -- control de cambios activo
    marca_agua VARCHAR(40) NOT NULL DEFAULT '',       -- texto diagonal detrás de cada página
    encabezado_texto VARCHAR(200) NOT NULL DEFAULT '',
    pie_texto VARCHAR(200) NOT NULL DEFAULT '',
    contenido LONGTEXT,                               -- HTML ya saneado (Classes\Redactor::limpiar)
    comentarios LONGTEXT NULL,                        -- JSON de comentarios
    creado_por INT DEFAULT NULL,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_proyecto (proyecto_id, departamento),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Historial de versiones del documento (ver database/migracion_redactor_herramientas.sql)
CREATE TABLE IF NOT EXISTS redactor_versiones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    documento_id INT NOT NULL,
    etiqueta VARCHAR(80) NOT NULL DEFAULT '',
    usuario VARCHAR(100) NOT NULL DEFAULT '',
    contenido LONGTEXT,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_documento (documento_id, creado),
    FOREIGN KEY (documento_id) REFERENCES redactor_documentos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Palabras que el usuario agrega al diccionario del corrector (una lista por proyecto)
CREATE TABLE IF NOT EXISTS redactor_palabras (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    palabra VARCHAR(60) NOT NULL,
    UNIQUE KEY uq_palabra (proyecto_id, palabra),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

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
ALTER TABLE vplan_tablas MODIFY COLUMN tipo ENUM('punch','restricciones','secundarias','otro','punchlist','materiales') NOT NULL DEFAULT 'punchlist';
ALTER TABLE vplan_tablas ADD COLUMN punch_config TEXT NULL;
ALTER TABLE vplan_tablas ADD COLUMN sheetflow_id INT NULL;
ALTER TABLE vplan_tablas ADD CONSTRAINT fk_vplan_tabla_sheetflow FOREIGN KEY (sheetflow_id) REFERENCES sheetflow_hojas(id) ON DELETE SET NULL;
ALTER TABLE vplan_laminas ADD CONSTRAINT fk_vplan_lamina_materiales FOREIGN KEY (materiales_tabla_id) REFERENCES vplan_tablas(id) ON DELETE SET NULL;

-- Historial de versiones de las tablas de SheetFlow (una copia del JSON por guardado con cambios)
CREATE TABLE IF NOT EXISTS sheetflow_versiones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hoja_id INT NOT NULL,
    usuario_id INT DEFAULT NULL,
    nombre VARCHAR(150) NOT NULL DEFAULT '',
    datos LONGTEXT,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_hoja (hoja_id, id),
    FOREIGN KEY (hoja_id) REFERENCES sheetflow_hojas(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Vínculo entre las subactividades de una actividad y su tabla de SheetFlow (sincronización en los dos sentidos)
CREATE TABLE IF NOT EXISTS subactividad_hojas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    actividad_id INT NOT NULL,
    hoja_id INT NOT NULL,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_subhoja_actividad (actividad_id),
    UNIQUE KEY uq_subhoja_hoja (hoja_id),
    KEY idx_subhoja_proyecto (proyecto_id),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE CASCADE,
    FOREIGN KEY (hoja_id) REFERENCES sheetflow_hojas(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Clima por proyecto (Open-Meteo): ubicación geocodificada y días de pronóstico / registro real, para anomalías y días no hábiles probables.
CREATE TABLE IF NOT EXISTS clima_proyecto (
    proyecto_id INT PRIMARY KEY,
    lugar VARCHAR(200) NOT NULL DEFAULT '',
    lat DECIMAL(8,5) NOT NULL,
    lon DECIMAL(8,5) NOT NULL,
    actualizado DATETIME NOT NULL,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS clima_dias (
    proyecto_id INT NOT NULL,
    fecha DATE NOT NULL,
    precip_mm DECIMAL(6,1) NOT NULL DEFAULT 0,
    prob INT NULL,                                   -- probabilidad de lluvia (solo pronóstico)
    viento_kmh DECIMAL(5,1) NOT NULL DEFAULT 0,
    tmax DECIMAL(4,1) NULL,
    tmin DECIMAL(4,1) NULL,
    tipo ENUM('pronostico','real') NOT NULL,
    actualizado DATETIME NOT NULL,
    PRIMARY KEY (proyecto_id, fecha),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Mapa de avance en planos: el color de las marcas del Editor (rojo/amarillo/verde) se traduce a un porcentaje de avance por actividad.
CREATE TABLE IF NOT EXISTS mapa_avance_ajustes (
    proyecto_id INT PRIMARY KEY,
    rojo TINYINT UNSIGNED NOT NULL DEFAULT 0,        -- % de avance que vale una marca roja (no avanzado)
    amarillo TINYINT UNSIGNED NOT NULL DEFAULT 50,   -- … amarilla (a medias)
    verde TINYINT UNSIGNED NOT NULL DEFAULT 100,     -- … verde (terminado)
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Qué capa de una lámina corresponde a qué actividad (capa_id vacío = todas las marcas sin capa asignada)
CREATE TABLE IF NOT EXISTS mapa_avance_capas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    lamina_id INT NOT NULL,
    capa_id VARCHAR(40) NOT NULL DEFAULT '',
    actividad_id INT NOT NULL,
    UNIQUE KEY uq_capa (lamina_id, capa_id, actividad_id),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (lamina_id) REFERENCES vplan_laminas(id) ON DELETE CASCADE,
    FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Una foto por día de lo que indican las marcas: sirve para ver el avance diario y su tendencia
CREATE TABLE IF NOT EXISTS mapa_avance_instantaneas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    lamina_id INT NOT NULL,
    actividad_id INT NOT NULL,
    fecha DATE NOT NULL,
    porcentaje DECIMAL(5,2) NOT NULL,
    peso DECIMAL(12,2) NOT NULL DEFAULT 0,
    detalle_json TEXT NULL,                          -- marcas rojas/amarillas/verdes
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_dia (lamina_id, actividad_id, fecha),
    KEY idx_act (proyecto_id, actividad_id, fecha),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (lamina_id) REFERENCES vplan_laminas(id) ON DELETE CASCADE,
    FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Seguridad e higiene (permisos de trabajo, incidentes) y punch list (pendientes de calidad con fotos antes/después).
CREATE TABLE IF NOT EXISTS permisos_tipos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    nombre VARCHAR(120) NOT NULL,
    palabras TEXT NULL,
    medidas_json TEXT NULL,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_proy (proyecto_id),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS permisos_trabajo (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    tipo VARCHAR(30) NOT NULL,                       -- altura | caliente | espacio_confinado | excavacion | electrico | izaje | general | t<id> (permisos_tipos)
    nombre_permiso VARCHAR(150) NOT NULL DEFAULT '',
    titulo VARCHAR(200) NOT NULL,
    actividad_id INT NULL,
    area VARCHAR(150) NOT NULL DEFAULT '',
    responsable VARCHAR(150) NOT NULL DEFAULT '',
    contratista VARCHAR(150) NOT NULL DEFAULT '',
    descripcion TEXT NULL,
    riesgos TEXT NULL,
    medidas_json TEXT NULL,                          -- [{texto, ok}]
    vigente_desde DATE NOT NULL,
    vigente_hasta DATE NOT NULL,
    estado ENUM('borrador','solicitado','aprobado','rechazado','cerrado') NOT NULL DEFAULT 'solicitado',
    origen ENUM('manual','ia') NOT NULL DEFAULT 'manual',
    solicitado_por VARCHAR(150) NOT NULL DEFAULT '',
    resuelto_por VARCHAR(150) NOT NULL DEFAULT '',
    resuelto_en DATETIME NULL,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_proy (proyecto_id, estado, vigente_hasta),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS seguridad_incidentes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    fecha DATE NOT NULL,
    tipo ENUM('incidente','casi_accidente','acto_inseguro','condicion_insegura') NOT NULL,
    severidad ENUM('baja','media','alta','critica') NOT NULL DEFAULT 'media',
    area VARCHAR(150) NOT NULL DEFAULT '',
    actividad_id INT NULL,
    reporte_id INT NULL,
    descripcion TEXT NOT NULL,
    acciones TEXT NULL,
    causa_inmediata TEXT NULL,
    causa_raiz TEXT NULL,
    acciones_correctivas TEXT NULL,
    dias_perdidos INT NOT NULL DEFAULT 0,
    costo DECIMAL(14,2) NOT NULL DEFAULT 0,
    investigado_por VARCHAR(150) NOT NULL DEFAULT '',
    cerrado_en DATETIME NULL,
    estado ENUM('abierto','cerrado') NOT NULL DEFAULT 'abierto',
    reportado_por VARCHAR(150) NOT NULL DEFAULT '',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_proy (proyecto_id, fecha),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE SET NULL,
    FOREIGN KEY (reporte_id) REFERENCES reportes_diarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS punch_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    titulo VARCHAR(200) NOT NULL,
    descripcion TEXT NULL,
    actividad_id INT NULL,
    ubicacion VARCHAR(200) NOT NULL DEFAULT '',
    categoria VARCHAR(60) NOT NULL DEFAULT '',
    severidad ENUM('baja','media','alta','critica') NOT NULL DEFAULT 'media',
    responsable VARCHAR(150) NOT NULL DEFAULT '',
    contratista VARCHAR(150) NOT NULL DEFAULT '',
    fecha_emision DATE NULL,                          -- día en que se emitió el pendiente (punch list)
    fecha_limite DATE NULL,
    estado ENUM('abierto','en_proceso','corregido','cerrado','rechazado') NOT NULL DEFAULT 'abierto',
    foto_antes VARCHAR(100) NULL,
    foto_despues VARCHAR(100) NULL,
    origen ENUM('manual','inspeccion','ia') NOT NULL DEFAULT 'manual',
    creado_por VARCHAR(150) NOT NULL DEFAULT '',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    cerrado_en DATETIME NULL,
    KEY idx_proy (proyecto_id, estado),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Comparador de documentos: comparaciones guardadas (documentos, vista, alineación y anotaciones).
-- Las copias de los documentos externos se guardan en public/uploads/comparador y la miniatura en min/.
CREATE TABLE IF NOT EXISTS comparador_guardados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    descripcion VARCHAR(500) NOT NULL DEFAULT '',
    a_json TEXT NOT NULL,                 -- {tipo: archivo|subido, id, archivo, nombre, ext, pagina}
    b_json TEXT NOT NULL,
    vista_json TEXT NOT NULL,             -- {x, y, k}
    ajuste_json TEXT NOT NULL,            -- {x, y, s, rot} del documento B
    modo_json TEXT NOT NULL,              -- {modo, pos, dir, fade}
    anotaciones_json MEDIUMTEXT NOT NULL, -- {ancho, alto, shapes: [...]}
    miniatura VARCHAR(100) NULL,
    creado_por VARCHAR(150) NOT NULL DEFAULT '',
    usuario_id INT NULL,
    token CHAR(48) NULL,                  -- enlace público de solo lectura (se crea al compartir)
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_cmp_token (token),
    KEY idx_proy (proyecto_id, actualizado),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Historial de versiones de cada comparación guardada (se crea una al actualizar).
CREATE TABLE IF NOT EXISTS comparador_versiones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    guardado_id INT NOT NULL,
    nombre VARCHAR(150) NOT NULL DEFAULT '',
    descripcion VARCHAR(500) NOT NULL DEFAULT '',
    vista_json TEXT NOT NULL,
    ajuste_json TEXT NOT NULL,
    modo_json TEXT NOT NULL,
    anotaciones_json MEDIUMTEXT NOT NULL,
    creado_por VARCHAR(150) NOT NULL DEFAULT '',
    usuario_id INT NULL,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_cmp_ver (guardado_id, creado),
    FOREIGN KEY (guardado_id) REFERENCES comparador_guardados(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Tipo de obra de cada proyecto (plantilla): da contexto a la IA, reglas de secuencia y una base de partidas/actividades.
CREATE TABLE IF NOT EXISTS proyecto_plantilla (
    proyecto_id INT PRIMARY KEY,
    plantilla VARCHAR(30) NOT NULL,
    base_aplicada TINYINT(1) NOT NULL DEFAULT 0,
    actualizado TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Registro de cada vez que se identificó o se eligió el tipo de obra de un proyecto (referencia futura)
CREATE TABLE IF NOT EXISTS proyecto_plantilla_historial (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    plantilla VARCHAR(30) NOT NULL,
    origen ENUM('identificado','manual') NOT NULL DEFAULT 'manual',
    confianza TINYINT UNSIGNED NOT NULL DEFAULT 0,       -- % de coincidencia al identificar
    indicios VARCHAR(500) NOT NULL DEFAULT '',           -- palabras del proyecto que lo sustentan
    usuario VARCHAR(150) NOT NULL DEFAULT '',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_proyecto (proyecto_id, creado),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Redactor: firmas, sellos y plantillas (ver database/migracion_redactor_revision.sql)
-- Firmas manuscritas guardadas por usuario (imagen en uploads/firmas)
CREATE TABLE IF NOT EXISTS redactor_firmas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    nombre VARCHAR(60) NOT NULL,
    archivo VARCHAR(40) NOT NULL,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_usuario (usuario_id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Sellos de firma electrónica interna: quién firmó, cuándo y la huella del texto e imágenes en ese momento
CREATE TABLE IF NOT EXISTS redactor_sellos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    documento_id INT NOT NULL,
    usuario_id INT DEFAULT NULL,
    nombre VARCHAR(100) NOT NULL,
    cargo VARCHAR(100) NOT NULL DEFAULT '',
    huella CHAR(64) NOT NULL,
    firmado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_doc (documento_id),
    FOREIGN KEY (documento_id) REFERENCES redactor_documentos(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Plantillas de documento creadas por el proyecto
CREATE TABLE IF NOT EXISTS redactor_plantillas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    descripcion VARCHAR(200) NOT NULL DEFAULT '',
    papel VARCHAR(10) NOT NULL DEFAULT 'carta',
    orientacion VARCHAR(10) NOT NULL DEFAULT 'vertical',
    margen_sup DECIMAL(4,2) NOT NULL DEFAULT 2.54,
    margen_inf DECIMAL(4,2) NOT NULL DEFAULT 2.54,
    margen_izq DECIMAL(4,2) NOT NULL DEFAULT 2.54,
    margen_der DECIMAL(4,2) NOT NULL DEFAULT 2.54,
    contenido LONGTEXT,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;
-- Revisiones por proyecto: veredicto de normativa/especificaciones (materiales y actividades) y estado de uso de las herramientas de IA.
CREATE TABLE IF NOT EXISTS revisiones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    tipo ENUM('material','actividad','herramienta','rfi') NOT NULL,
    ref VARCHAR(60) NOT NULL,                          -- id del material/actividad o clave de la herramienta
    veredicto ENUM('sin_evaluar','correcto','revisar','no_cumple') NOT NULL DEFAULT 'sin_evaluar',
    revisado TINYINT(1) NOT NULL DEFAULT 0,
    huella CHAR(32) NOT NULL DEFAULT '',               -- datos del elemento al revisar; si cambian, la revisión queda desactualizada
    nota VARCHAR(500) NOT NULL DEFAULT '',
    usuario_id INT NULL,
    usuario VARCHAR(150) NOT NULL DEFAULT '',
    ejecutada_en DATETIME NULL,                        -- herramientas: última ejecución
    huella_ejecucion CHAR(32) NOT NULL DEFAULT '',     -- herramientas: datos del proyecto en esa ejecución
    actualizado TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_revision (proyecto_id, tipo, ref),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Historial de cambios del estado: solo se agregan filas
CREATE TABLE IF NOT EXISTS revisiones_historial (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    tipo ENUM('material','actividad','herramienta','rfi') NOT NULL,
    ref VARCHAR(60) NOT NULL,
    nombre VARCHAR(255) NOT NULL DEFAULT '',
    veredicto_antes VARCHAR(20) NOT NULL DEFAULT 'sin_evaluar',
    veredicto_despues VARCHAR(20) NOT NULL DEFAULT 'sin_evaluar',
    revisado_antes TINYINT(1) NOT NULL DEFAULT 0,
    revisado_despues TINYINT(1) NOT NULL DEFAULT 0,
    nota VARCHAR(500) NOT NULL DEFAULT '',
    usuario VARCHAR(150) NOT NULL DEFAULT '',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_proyecto_fecha (proyecto_id, creado),
    KEY idx_elemento (proyecto_id, tipo, ref),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Órdenes de cambio y líneas base (ver database/migracion_cambios.sql)
CREATE TABLE ordenes_cambio (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    numero VARCHAR(30) NOT NULL DEFAULT '',
    titulo VARCHAR(200) NOT NULL,
    descripcion TEXT NULL,
    solicitante VARCHAR(150) NOT NULL DEFAULT '',
    estado ENUM('borrador','solicitada','aprobada','rechazada','ejecutada','cerrada') NOT NULL DEFAULT 'borrador',
    fecha DATE NOT NULL,
    impacto_costo DECIMAL(14,2) NOT NULL DEFAULT 0,
    impacto_dias INT NOT NULL DEFAULT 0,
    actividad_id INT NULL,                         -- actividad principal afectada (opcional)
    subactividad_id INT NULL,                      -- subactividad principal afectada (opcional)
    creado_por VARCHAR(150) NOT NULL DEFAULT '',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_oc_proyecto (proyecto_id, estado),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE SET NULL,
    FOREIGN KEY (subactividad_id) REFERENCES subactividades(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE cambio_partidas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    orden_id INT NOT NULL,
    proyecto_id INT NOT NULL,
    tipo ENUM('instalacion_temporal','instalacion','reubicacion','demolicion','adicion','modificacion') NOT NULL DEFAULT 'modificacion',
    descripcion VARCHAR(300) NOT NULL,
    ubicacion_original VARCHAR(200) NOT NULL DEFAULT '',
    ubicacion_nueva VARCHAR(200) NOT NULL DEFAULT '',
    cantidad DECIMAL(12,2) NULL,
    unidad VARCHAR(30) NOT NULL DEFAULT '',
    actividad_id INT NULL,
    subactividad_id INT NULL,
    material_id INT NULL,
    requiere_retiro TINYINT(1) NOT NULL DEFAULT 0,
    estado ENUM('pendiente','ejecutado','retirado','verificado','cancelado') NOT NULL DEFAULT 'pendiente',
    fecha_prevista DATE NULL,
    fecha_ejecucion DATE NULL,
    fecha_retiro_prevista DATE NULL,
    fecha_retiro_real DATE NULL,
    notas VARCHAR(500) NOT NULL DEFAULT '',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_cp_proyecto (proyecto_id, estado),
    FOREIGN KEY (orden_id) REFERENCES ordenes_cambio(id) ON DELETE CASCADE,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE SET NULL,
    FOREIGN KEY (subactividad_id) REFERENCES subactividades(id) ON DELETE SET NULL,
    FOREIGN KEY (material_id) REFERENCES materiales(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Líneas base: foto inmutable del programa (sin llaves foráneas a actividades: la foto sobrevive aunque se borre la actividad)
CREATE TABLE lineas_base (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    notas VARCHAR(500) NOT NULL DEFAULT '',
    fin_programado DATE NULL,
    total_actividades INT NOT NULL DEFAULT 0,
    total_subactividades INT NOT NULL DEFAULT 0,
    criticas_json TEXT NULL,                       -- ids de actividades en ruta crítica al capturar
    creado_por VARCHAR(150) NOT NULL DEFAULT '',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_lb_proyecto (proyecto_id),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE lineas_base_actividades (
    linea_id INT NOT NULL,
    actividad_id INT NOT NULL,
    codigo VARCHAR(50) NOT NULL DEFAULT '',
    nombre VARCHAR(200) NOT NULL DEFAULT '',
    partida VARCHAR(200) NOT NULL DEFAULT '',
    fecha_inicio DATE NOT NULL,
    fecha_fin DATE NOT NULL,
    duracion_dias INT NOT NULL DEFAULT 1,
    progreso TINYINT UNSIGNED NOT NULL DEFAULT 0,
    hito TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (linea_id, actividad_id),
    FOREIGN KEY (linea_id) REFERENCES lineas_base(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE lineas_base_subactividades (
    linea_id INT NOT NULL,
    subactividad_id INT NOT NULL,
    actividad_id INT NOT NULL,
    codigo VARCHAR(60) NOT NULL DEFAULT '',
    nombre VARCHAR(200) NOT NULL DEFAULT '',
    partida VARCHAR(200) NOT NULL DEFAULT '',
    fecha_inicio DATE NOT NULL,
    fecha_fin DATE NOT NULL,
    duracion_dias INT NOT NULL DEFAULT 1,
    progreso TINYINT UNSIGNED NOT NULL DEFAULT 0,
    hito TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (linea_id, subactividad_id),
    KEY idx_lbsub_actividad (linea_id, actividad_id),
    FOREIGN KEY (linea_id) REFERENCES lineas_base(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE lineas_base_materiales (
    linea_id INT NOT NULL,
    material_id INT NOT NULL,
    nombre VARCHAR(255) NOT NULL DEFAULT '',
    cantidad DECIMAL(12,2) NULL,
    fecha_compra_prog DATE NULL,
    fecha_entrega_prog DATE NULL,
    PRIMARY KEY (linea_id, material_id),
    FOREIGN KEY (linea_id) REFERENCES lineas_base(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- ENSAMBLADOR (documentos → programa, materiales y submittals)
-- ============================================================
-- Ensamblador (app de escritorio): documentos analizados por la IA y propuestas para el programa, materiales y submittals
CREATE TABLE IF NOT EXISTS ensamblador_sesiones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    usuario VARCHAR(150) NOT NULL DEFAULT '',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ensamblador_archivos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sesion_id INT NOT NULL,
    proyecto_id INT NOT NULL,
    nombre VARCHAR(255) NOT NULL,
    hash CHAR(64) NOT NULL,
    tamano INT NOT NULL DEFAULT 0,
    paginas INT NOT NULL DEFAULT 0,
    caracteres INT NOT NULL DEFAULT 0,
    texto MEDIUMTEXT,                              -- páginas separadas por \f
    aviso VARCHAR(255) NOT NULL DEFAULT '',
    archivo_id INT DEFAULT NULL,                   -- copia guardada en el Archivero
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ens_arch_sesion (sesion_id),
    FOREIGN KEY (sesion_id) REFERENCES ensamblador_sesiones(id) ON DELETE CASCADE,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ensamblador_propuestas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sesion_id INT NOT NULL,
    proyecto_id INT NOT NULL,
    instruccion VARCHAR(1000) NOT NULL DEFAULT '',
    modos VARCHAR(200) NOT NULL DEFAULT '',
    modelo VARCHAR(150) NOT NULL DEFAULT '',
    items_json MEDIUMTEXT NOT NULL,                -- [{id, tipo, titulo, detalle, confianza, datos, estado, antes}]
    estado ENUM('pendiente','aplicada','revertida','rechazada') NOT NULL DEFAULT 'pendiente',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sesion_id) REFERENCES ensamblador_sesiones(id) ON DELETE CASCADE,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;
-- Fechas contractuales (inicio, fin, hitos con penalización) extraídas de contratos y bases de licitación
CREATE TABLE IF NOT EXISTS proyecto_fechas_contrato (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    tipo ENUM('inicio','fin','hito') NOT NULL DEFAULT 'hito',
    nombre VARCHAR(200) NOT NULL DEFAULT '',
    fecha DATE NOT NULL,
    penalizacion VARCHAR(200) NOT NULL DEFAULT '',
    origen VARCHAR(255) NOT NULL DEFAULT '',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_fc_proyecto (proyecto_id),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Cantidades de obra (catálogo de conceptos) por actividad; la Curva S pesa por importe cuando todas las actividades lo tienen
CREATE TABLE IF NOT EXISTS actividad_cantidades (
    actividad_id INT NOT NULL PRIMARY KEY,
    proyecto_id INT NOT NULL,
    clave VARCHAR(60) NOT NULL DEFAULT '',
    concepto VARCHAR(255) NOT NULL DEFAULT '',
    cantidad DECIMAL(14,3) NULL,
    precio_unitario DECIMAL(14,2) NULL,
    unidad VARCHAR(30) NOT NULL DEFAULT '',
    importe DECIMAL(14,2) NULL,
    origen VARCHAR(255) NOT NULL DEFAULT '',
    KEY idx_ac_proyecto (proyecto_id),
    FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE CASCADE,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Datos de contacto del personal del proyecto (directorios y minutas)
CREATE TABLE IF NOT EXISTS personal_contacto (
    personal_id INT NOT NULL PRIMARY KEY,
    cargo VARCHAR(100) NOT NULL DEFAULT '',
    empresa VARCHAR(150) NOT NULL DEFAULT '',
    telefono VARCHAR(40) NOT NULL DEFAULT '',
    correo VARCHAR(150) NOT NULL DEFAULT '',
    direccion VARCHAR(250) NOT NULL DEFAULT '',
    rfc VARCHAR(13) NOT NULL DEFAULT '',
    tipo VARCHAR(20) NOT NULL DEFAULT '',   -- directivo | administrativo | operativo
    notas TEXT NULL,
    FOREIGN KEY (personal_id) REFERENCES personal(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Responsable de cada actividad (una persona del personal del proyecto)
CREATE TABLE IF NOT EXISTS actividad_responsables (
    actividad_id INT NOT NULL PRIMARY KEY,
    personal_id INT NOT NULL,
    FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE CASCADE,
    FOREIGN KEY (personal_id) REFERENCES personal(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Ensamblador de documentos (Documentos y diseño): PDF que une documentos del Redactor y PDF del Archivo.
-- `fuentes` es la receta (JSON [{"t":"redactor"|"archivo","id":N}, ...]); el PDF se genera con las fuentes actuales.
-- `archivo_id` = copia guardada en Archivo, que se regenera sola cuando cambia una fuente (`firma` = huella de las fuentes).
CREATE TABLE IF NOT EXISTS ensambles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    fuentes TEXT NOT NULL,
    archivo_id INT DEFAULT NULL,
    firma CHAR(40) NOT NULL DEFAULT '',
    creado_por INT DEFAULT NULL,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_proyecto (proyecto_id),
    UNIQUE KEY uq_archivo (archivo_id),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (archivo_id) REFERENCES archivos(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Lookahead configurable por proyecto (nombre, PTA, semanas visibles, título y plantilla de encabezado)
CREATE TABLE IF NOT EXISTS lookahead_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    nombre VARCHAR(60) NOT NULL DEFAULT '',
    pta_nombre VARCHAR(20) NOT NULL DEFAULT '',
    semanas TINYINT NOT NULL DEFAULT 4,          -- semanas que se ven después de la actual (1-8)
    titulo VARCHAR(150) NOT NULL DEFAULT '',     -- banner; admite {semanas} {proyecto} {codigo}
    encabezado_id INT DEFAULT NULL,
    UNIQUE KEY uq_proyecto (proyecto_id),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Directorio (Administración): categorías de Punch y Evidencias (ver database/migracion_directorio.sql)
CREATE TABLE IF NOT EXISTS catalogo_categorias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    ambito VARCHAR(20) NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_cat_ambito (proyecto_id, ambito, nombre),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Fase 3: costos y presupuesto, recursos y cuadrillas, RFI, enlaces de solo lectura (cliente y calendario .ics)

-- 3.1 Costos: catálogo de conceptos, presupuesto por partida y movimientos

-- Catálogo de conceptos: cantidad contractual × precio unitario, agrupado por partida
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

-- Cuánto de cada concepto ejecuta cada actividad (muchos a muchos)
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

CREATE TABLE IF NOT EXISTS costos_partidas (
    partida_id INT NOT NULL PRIMARY KEY,
    proyecto_id INT NOT NULL,
    presupuesto DECIMAL(16,2) NOT NULL DEFAULT 0,
    presupuesto_manual DECIMAL(16,2) NULL,           -- presupuesto manual anterior (legado) o total si la partida no tiene conceptos
    ajuste DECIMAL(16,2) NOT NULL DEFAULT 0,         -- línea de ajuste que se suma al catálogo
    origen ENUM('manual','cantidades','conceptos') NOT NULL DEFAULT 'manual',
    KEY idx_cp_proyecto (proyecto_id),
    FOREIGN KEY (partida_id) REFERENCES partidas(id) ON DELETE CASCADE,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Cargos del proyecto sobre el subtotal directo del catálogo
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

CREATE TABLE IF NOT EXISTS costos_movimientos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    partida_id INT NULL,
    tipo ENUM('real','estimacion') NOT NULL DEFAULT 'real',
    fecha DATE NOT NULL,
    concepto VARCHAR(255) NOT NULL DEFAULT '',
    referencia VARCHAR(100) NOT NULL DEFAULT '',     -- factura, número de estimación…
    monto DECIMAL(16,2) NOT NULL DEFAULT 0,
    estado ENUM('borrador','aprobada','pagada') NOT NULL DEFAULT 'aprobada',   -- solo estimaciones
    orden_cambio_id INT NULL,
    usuario VARCHAR(150) NOT NULL DEFAULT '',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_cm_proyecto (proyecto_id, tipo, fecha),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (partida_id) REFERENCES partidas(id) ON DELETE SET NULL,
    FOREIGN KEY (orden_cambio_id) REFERENCES ordenes_cambio(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 3.2 Recursos: personas y cuadrillas, y su asignación a actividades
CREATE TABLE IF NOT EXISTS recursos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    tipo ENUM('persona','cuadrilla') NOT NULL DEFAULT 'persona',
    personal_id INT NULL,                            -- persona del Directorio (opcional)
    integrantes SMALLINT NOT NULL DEFAULT 1,         -- tamaño de la cuadrilla
    horas_dia DECIMAL(4,1) NOT NULL DEFAULT 8,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_rec_proyecto (proyecto_id),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (personal_id) REFERENCES personal(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS actividad_recursos (
    actividad_id INT NOT NULL,
    recurso_id INT NOT NULL,
    dedicacion SMALLINT NOT NULL DEFAULT 100,        -- % de su jornada mientras dura la actividad
    PRIMARY KEY (actividad_id, recurso_id),
    FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE CASCADE,
    FOREIGN KEY (recurso_id) REFERENCES recursos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 3.3 RFI (solicitudes de información)
CREATE TABLE IF NOT EXISTS rfis (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    numero VARCHAR(30) NOT NULL,
    asunto VARCHAR(200) NOT NULL,
    pregunta TEXT,
    respuesta TEXT,
    responsable VARCHAR(150) NOT NULL DEFAULT '',    -- quien debe contestar
    solicitante VARCHAR(150) NOT NULL DEFAULT '',
    prioridad ENUM('baja','media','alta','critica') NOT NULL DEFAULT 'media',
    estado ENUM('abierto','respondido','cerrado') NOT NULL DEFAULT 'abierto',
    fecha DATE NOT NULL,
    fecha_limite DATE NULL,
    fecha_respuesta DATE NULL,
    actividad_id INT NULL,                           -- actividad que bloquea
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_rfi_proyecto (proyecto_id, estado),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Láminas del Editor (/graficos) adjuntas a un RFI (ver database/migracion_rfi_laminas.sql)
CREATE TABLE IF NOT EXISTS rfi_laminas (
    rfi_id INT NOT NULL,
    lamina_id INT NOT NULL,
    orden INT NOT NULL DEFAULT 0,
    PRIMARY KEY (rfi_id, lamina_id),
    FOREIGN KEY (rfi_id) REFERENCES rfis(id) ON DELETE CASCADE,
    FOREIGN KEY (lamina_id) REFERENCES editor_laminas(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 3.4 y 3.5 Enlaces de solo lectura: para el cliente (temporal) y suscripción de calendario .ics
CREATE TABLE IF NOT EXISTS enlaces_proyecto (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    tipo ENUM('cliente','ics') NOT NULL DEFAULT 'cliente',
    token CHAR(40) NOT NULL,
    nombre VARCHAR(150) NOT NULL DEFAULT '',
    secciones VARCHAR(100) NOT NULL DEFAULT 'avance,fotos,reportes',
    expira DATETIME NULL,
    revocado TINYINT(1) NOT NULL DEFAULT 0,
    accesos INT NOT NULL DEFAULT 0,
    ultimo_acceso DATETIME NULL,
    creado_por VARCHAR(150) NOT NULL DEFAULT '',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_enlace_token (token),
    KEY idx_enlace_proyecto (proyecto_id),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- FASE 4: PERSONALIZACIÓN
-- ============================================================
-- 4.1 Diseño de columnas (nombre, orden, visibilidad) de Lookahead, Punch, Submittals, Bitácora y Reporte diario
CREATE TABLE IF NOT EXISTS disenos_lista (
    proyecto_id INT NOT NULL,
    herramienta VARCHAR(30) NOT NULL,
    columnas_json TEXT NOT NULL,
    PRIMARY KEY (proyecto_id, herramienta),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 4.2 Campos personalizados por proyecto (Nivel, Frente, Torre…) y sus valores por registro
CREATE TABLE IF NOT EXISTS campos_personalizados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    entidad ENUM('actividad','punch','submittal','bitacora','reporte') NOT NULL,
    nombre VARCHAR(60) NOT NULL,
    tipo ENUM('texto','numero','fecha','lista') NOT NULL DEFAULT 'texto',
    opciones TEXT NULL,                       -- tipo lista: una opción por línea
    orden INT NOT NULL DEFAULT 0,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_cpers (proyecto_id, entidad),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS campos_valores (
    campo_id INT NOT NULL,
    registro_id INT NOT NULL,
    valor VARCHAR(500) NOT NULL DEFAULT '',
    PRIMARY KEY (campo_id, registro_id),
    FOREIGN KEY (campo_id) REFERENCES campos_personalizados(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 4.3 Plantillas de proyecto completas (estructura de un proyecto terminado, lista para crear uno nuevo)
CREATE TABLE IF NOT EXISTS plantillas_proyecto (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    descripcion VARCHAR(500) NOT NULL DEFAULT '',
    compartida TINYINT(1) NOT NULL DEFAULT 0,          -- 1 = la ven todos los que pueden crear proyectos
    origen VARCHAR(200) NOT NULL DEFAULT '',            -- nombre del proyecto del que salió
    resumen_json TEXT NULL,                             -- conteos para mostrar en la lista
    datos LONGTEXT NOT NULL,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_pp_usuario (usuario_id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- FASE 5: API REST Y WEBHOOKS
-- ============================================================
-- 5.1 Tokens de la API: se guarda solo el hash (SHA-256); el token completo se muestra una vez al crearlo
CREATE TABLE IF NOT EXISTS api_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    token_hash CHAR(64) NOT NULL,
    prefijo VARCHAR(12) NOT NULL,                  -- primeros caracteres, para reconocerlo en la lista
    escritura TINYINT(1) NOT NULL DEFAULT 0,       -- 0 = solo lectura
    proyecto_id INT NULL,                          -- NULL = todos los proyectos del usuario
    expira DATE NULL,
    revocado TINYINT(1) NOT NULL DEFAULT 0,
    usos INT NOT NULL DEFAULT 0,
    ultimo_uso DATETIME NULL,
    ventana INT NOT NULL DEFAULT 0,                -- minuto (epoch/60) del límite de uso
    ventana_n INT NOT NULL DEFAULT 0,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_api_hash (token_hash),
    KEY idx_api_usuario (usuario_id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 5.1 Webhooks: avisos HTTP firmados (HMAC-SHA256) cuando algo cambia en un proyecto
CREATE TABLE IF NOT EXISTS webhooks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    nombre VARCHAR(100) NOT NULL DEFAULT '',
    url VARCHAR(500) NOT NULL,
    secreto CHAR(40) NOT NULL,
    eventos VARCHAR(500) NOT NULL DEFAULT '*',     -- lista separada por comas, o * para todos
    activo TINYINT(1) NOT NULL DEFAULT 1,
    fallos_seguidos INT NOT NULL DEFAULT 0,        -- al llegar a 10 se desactiva solo
    creado_por VARCHAR(150) NOT NULL DEFAULT '',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_wh_proyecto (proyecto_id, activo),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS webhook_entregas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    webhook_id INT NOT NULL,
    evento VARCHAR(60) NOT NULL,
    codigo_http SMALLINT NOT NULL DEFAULT 0,
    ok TINYINT(1) NOT NULL DEFAULT 0,
    ms INT NOT NULL DEFAULT 0,
    error VARCHAR(255) NOT NULL DEFAULT '',
    carga TEXT NULL,                               -- el JSON enviado (para reintentar)
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_whe (webhook_id, id),
    FOREIGN KEY (webhook_id) REFERENCES webhooks(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS telegram_vinculos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    chat_id BIGINT NOT NULL,
    nombre_tg VARCHAR(150) NOT NULL DEFAULT '',
    proyecto_id INT NULL,                          -- proyecto activo para /avance, /foto, /nota
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tg_usuario (usuario_id),
    UNIQUE KEY uq_tg_chat (chat_id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Códigos de un solo uso (vencen en 15 min) para vincular un chat privado (tipo usuario) o un grupo a un proyecto (tipo grupo)
CREATE TABLE IF NOT EXISTS telegram_codigos (
    codigo CHAR(12) PRIMARY KEY,
    usuario_id INT NOT NULL,
    tipo ENUM('usuario','grupo') NOT NULL DEFAULT 'usuario',
    proyecto_id INT NULL,
    expira DATETIME NOT NULL,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Dónde se avisa de cada proyecto: un grupo o el chat privado de una persona (usuario_id)
CREATE TABLE IF NOT EXISTS telegram_destinos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    chat_id BIGINT NOT NULL,
    titulo VARCHAR(150) NOT NULL DEFAULT '',
    usuario_id INT NULL,                           -- NULL = grupo
    eventos VARCHAR(500) NOT NULL DEFAULT '',      -- lista separada por comas
    activo TINYINT(1) NOT NULL DEFAULT 1,
    fallos_seguidos INT NOT NULL DEFAULT 0,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tg_destino (proyecto_id, chat_id),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Conversación de cada chat privado (paso, datos temporales) y último update atendido (evita repetir si Telegram reintenta)
CREATE TABLE IF NOT EXISTS telegram_estado (
    chat_id BIGINT PRIMARY KEY,
    paso VARCHAR(20) NOT NULL DEFAULT '',
    datos TEXT NULL,
    ultimo_update BIGINT NOT NULL DEFAULT 0,
    actualizado DATETIME NOT NULL
) ENGINE=InnoDB;

-- Configuración única del bot: token CIFRADO y @usuario, editables desde la interfaz (Integraciones → Telegram,
-- solo admin) para no tener que tocar el clúster para rotarlo. Una sola fila (id = 1).
-- El cifrado usa TELEGRAM_KEY (entorno) o storage/secretos/telegram.key (fuera de public y del repo).
CREATE TABLE IF NOT EXISTS telegram_config (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    token_cifrado MEDIUMBLOB NOT NULL,
    bot_username VARCHAR(100) NOT NULL DEFAULT '',
    actualizado DATETIME NOT NULL,
    actualizado_por VARCHAR(150) NOT NULL DEFAULT ''
) ENGINE=InnoDB;

-- Límite de peticiones al webhook por IP (Telegram::limite): ventana de un minuto, para que nadie martille /api/telegram/webhook
CREATE TABLE IF NOT EXISTS telegram_limites (
    ip VARCHAR(45) NOT NULL,              -- IPv4/IPv6 (la IP real viene de X-Forwarded-For vía ip_cliente())
    ventana INT NOT NULL,                 -- minuto epoch (UNIX_TIMESTAMP() DIV 60)
    n INT NOT NULL DEFAULT 0,             -- peticiones de esa IP en esa ventana
    PRIMARY KEY (ip, ventana)
) ENGINE=InnoDB;
-- Herramientas de administración de obra: Calidad (ITP), Equipo y maquinaria,
-- Contratos/fianzas/seguros y Cierre de obra. Idempotente.
--
--   mysql -u root project < database/migracion_calidad_equipo_contratos_cierre.sql

-- ============================================================
-- CALIDAD E INSPECCIONES (ITP)
-- ============================================================
CREATE TABLE IF NOT EXISTS calidad_planes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    actividad_id INT NULL,
    codigo VARCHAR(40) NOT NULL DEFAULT '',
    titulo VARCHAR(200) NOT NULL,
    tipo ENUM('itp','inspeccion','prueba') NOT NULL DEFAULT 'itp',
    norma VARCHAR(200) NOT NULL DEFAULT '',
    responsable VARCHAR(150) NOT NULL DEFAULT '',
    fecha DATE NULL,
    estado ENUM('pendiente','en_proceso','conforme','no_conforme','cerrado') NOT NULL DEFAULT 'pendiente',
    bloquea TINYINT(1) NOT NULL DEFAULT 1,   -- el plan detiene el avance de su actividad
    notas TEXT,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_calidad_proyecto (proyecto_id, estado, fecha),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS calidad_puntos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    plan_id INT NOT NULL,
    orden INT NOT NULL DEFAULT 0,
    descripcion VARCHAR(500) NOT NULL,
    criterio VARCHAR(300) NOT NULL DEFAULT '',
    resultado ENUM('pendiente','cumple','no_cumple','na') NOT NULL DEFAULT 'pendiente',
    observacion VARCHAR(1000) NOT NULL DEFAULT '',
    fecha DATE NULL,
    responsable VARCHAR(150) NOT NULL DEFAULT '',
    resuelto TINYINT(1) NOT NULL DEFAULT 0,
    KEY idx_calidad_puntos_plan (plan_id, orden),
    FOREIGN KEY (plan_id) REFERENCES calidad_planes(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS calidad_liberaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    actividad_id INT NOT NULL,
    plan_id INT NULL,
    progreso_autorizado INT NOT NULL DEFAULT 100,
    nc_snapshot INT NOT NULL DEFAULT 0,   -- no conformidades abiertas al liberar: si aparece una nueva, la liberación caduca
    motivo VARCHAR(500) NOT NULL DEFAULT '',
    usuario VARCHAR(150) NOT NULL DEFAULT '',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_clib_actividad (proyecto_id, actividad_id, id),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE CASCADE,
    FOREIGN KEY (plan_id) REFERENCES calidad_planes(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- EQUIPO Y MAQUINARIA
-- ============================================================
CREATE TABLE IF NOT EXISTS equipo (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    tipo VARCHAR(80) NOT NULL DEFAULT '',
    marca VARCHAR(80) NOT NULL DEFAULT '',
    modelo VARCHAR(80) NOT NULL DEFAULT '',
    serie VARCHAR(80) NOT NULL DEFAULT '',
    propietario VARCHAR(150) NOT NULL DEFAULT '',
    rentado TINYINT(1) NOT NULL DEFAULT 0,
    tarifa_hora DECIMAL(12,2) NOT NULL DEFAULT 0,
    proximo_servicio DATE NULL,
    proximo_servicio_horas INT NULL,
    estado ENUM('operativo','mantenimiento','baja') NOT NULL DEFAULT 'operativo',
    notas VARCHAR(1000) NOT NULL DEFAULT '',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_equipo_proyecto (proyecto_id, estado),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS equipo_horas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    equipo_id INT NOT NULL,
    actividad_id INT NULL,
    reporte_id INT NULL,
    fecha DATE NOT NULL,
    horas DECIMAL(6,2) NOT NULL DEFAULT 0,
    operador VARCHAR(150) NOT NULL DEFAULT '',
    observaciones VARCHAR(1000) NOT NULL DEFAULT '',
    importe DECIMAL(12,2) NOT NULL DEFAULT 0,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_equipo_horas (proyecto_id, fecha),
    KEY idx_equipo_horas_equipo (equipo_id, fecha),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (equipo_id) REFERENCES equipo(id) ON DELETE CASCADE,
    FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE SET NULL,
    FOREIGN KEY (reporte_id) REFERENCES reportes_diarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- CONTRATOS, FIANZAS, SEGUROS Y LICENCIAS
-- ============================================================
CREATE TABLE IF NOT EXISTS contratos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    tipo ENUM('contrato','fianza','seguro','licencia','otro') NOT NULL DEFAULT 'contrato',
    numero VARCHAR(80) NOT NULL DEFAULT '',
    contraparte VARCHAR(200) NOT NULL DEFAULT '',
    objeto VARCHAR(300) NOT NULL DEFAULT '',
    monto DECIMAL(16,2) NOT NULL DEFAULT 0,
    moneda VARCHAR(3) NOT NULL DEFAULT 'MXN',
    inicio DATE NULL,
    vencimiento DATE NULL,
    aviso_dias SMALLINT NOT NULL DEFAULT 30,
    estado ENUM('vigente','cerrado') NOT NULL DEFAULT 'vigente',
    archivo_id INT NULL,
    notas TEXT,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_contratos_proyecto (proyecto_id, estado, vencimiento),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (archivo_id) REFERENCES archivos(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- CIERRE DE OBRA (entrega-recepción)
-- ============================================================
CREATE TABLE IF NOT EXISTS cierre_obra (
    proyecto_id INT NOT NULL PRIMARY KEY,
    fecha_entrega DATE NULL,
    responsable VARCHAR(150) NOT NULL DEFAULT '',
    notas TEXT,
    actualizado TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS cierre_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    categoria ENUM('as_built','garantia','manual','punch','documento','otro') NOT NULL DEFAULT 'documento',
    titulo VARCHAR(250) NOT NULL,
    responsable VARCHAR(150) NOT NULL DEFAULT '',
    fecha_compromiso DATE NULL,
    estado ENUM('pendiente','en_proceso','completado','na') NOT NULL DEFAULT 'pendiente',
    archivo_id INT NULL,
    notas VARCHAR(1000) NOT NULL DEFAULT '',
    orden INT NOT NULL DEFAULT 0,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_cierre_proyecto (proyecto_id, estado),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (archivo_id) REFERENCES archivos(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- ANOTADOR DE PLANOS Y DOCUMENTOS
-- ============================================================
CREATE TABLE IF NOT EXISTS anotador_documentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    descripcion VARCHAR(500) NOT NULL DEFAULT '',
    fuente_tipo VARCHAR(20) NOT NULL DEFAULT 'archivo',
    fuente_id INT NULL,
    archivo VARCHAR(100) NULL,
    nombre_archivo VARCHAR(255) NOT NULL DEFAULT '',
    ext VARCHAR(10) NOT NULL DEFAULT '',
    paginas INT NOT NULL DEFAULT 1,
    config_json TEXT NOT NULL,
    anotaciones_json MEDIUMTEXT NOT NULL,
    miniatura VARCHAR(100) NULL,
    creado_por VARCHAR(150) NOT NULL DEFAULT '',
    usuario_id INT NULL,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_ant_proyecto (proyecto_id, actualizado),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS anotador_versiones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    documento_id INT NOT NULL,
    nombre VARCHAR(150) NOT NULL DEFAULT '',
    config_json TEXT NOT NULL,
    anotaciones_json MEDIUMTEXT NOT NULL,
    miniatura VARCHAR(100) NULL,
    creado_por VARCHAR(150) NOT NULL DEFAULT '',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ant_ver (documento_id, id),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (documento_id) REFERENCES anotador_documentos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS anotador_firmas_aplicadas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    documento_id INT NOT NULL,
    usuario_id INT NULL,
    nombre VARCHAR(150) NOT NULL DEFAULT '',
    cargo VARCHAR(150) NOT NULL DEFAULT '',
    pagina INT NOT NULL DEFAULT 1,
    hash CHAR(64) NOT NULL DEFAULT '',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ant_firma (documento_id, id),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (documento_id) REFERENCES anotador_documentos(id) ON DELETE CASCADE
) ENGINE=InnoDB;


-- ============================================================
-- PARTE DIARIO ÚNICO DE CAMPO (tablas hijas de reportes_diarios)
-- Se crean al final para que existan personal, equipo, materiales
-- y seguridad_incidentes, a las que hacen referencia.
-- ============================================================

CREATE TABLE IF NOT EXISTS reporte_personal (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reporte_id INT NOT NULL,
    personal_id INT NULL,
    nombre VARCHAR(150) NOT NULL DEFAULT '',
    actividad_id INT NULL,
    horas_normal DECIMAL(5,2) NOT NULL DEFAULT 0,
    horas_extra DECIMAL(5,2) NOT NULL DEFAULT 0,
    cuadrilla VARCHAR(100) NOT NULL DEFAULT '',
    observacion VARCHAR(500) NOT NULL DEFAULT '',
    KEY idx_rp_reporte (reporte_id),
    FOREIGN KEY (reporte_id) REFERENCES reportes_diarios(id) ON DELETE CASCADE,
    FOREIGN KEY (personal_id) REFERENCES personal(id) ON DELETE SET NULL,
    FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS reporte_equipo (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reporte_id INT NOT NULL,
    equipo_id INT NULL,
    nombre VARCHAR(150) NOT NULL DEFAULT '',
    actividad_id INT NULL,
    horas DECIMAL(5,2) NOT NULL DEFAULT 0,
    operador VARCHAR(150) NOT NULL DEFAULT '',
    importe DECIMAL(12,2) NOT NULL DEFAULT 0,
    KEY idx_re_reporte (reporte_id),
    FOREIGN KEY (reporte_id) REFERENCES reportes_diarios(id) ON DELETE CASCADE,
    FOREIGN KEY (equipo_id) REFERENCES equipo(id) ON DELETE SET NULL,
    FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS reporte_materiales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reporte_id INT NOT NULL,
    material_id INT NULL,
    nombre VARCHAR(255) NOT NULL DEFAULT '',
    actividad_id INT NULL,
    cantidad DECIMAL(14,3) NOT NULL DEFAULT 0,
    unidad VARCHAR(30) NOT NULL DEFAULT '',
    tipo ENUM('recibido','consumido','devuelto') NOT NULL DEFAULT 'consumido',
    proveedor VARCHAR(150) NOT NULL DEFAULT '',
    remision VARCHAR(80) NOT NULL DEFAULT '',
    KEY idx_rm_reporte (reporte_id),
    FOREIGN KEY (reporte_id) REFERENCES reportes_diarios(id) ON DELETE CASCADE,
    FOREIGN KEY (material_id) REFERENCES materiales(id) ON DELETE SET NULL,
    FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS reporte_tiempos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reporte_id INT NOT NULL,
    actividad_id INT NULL,
    horas DECIMAL(5,2) NOT NULL DEFAULT 0,
    causa ENUM('lluvia','falta_material','espera','falla_equipo','seguridad','otra') NOT NULL DEFAULT 'otra',
    nota VARCHAR(500) NOT NULL DEFAULT '',
    KEY idx_rt_reporte (reporte_id),
    FOREIGN KEY (reporte_id) REFERENCES reportes_diarios(id) ON DELETE CASCADE,
    FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS reporte_seguridad (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reporte_id INT NOT NULL UNIQUE,
    charla_tema VARCHAR(255) NOT NULL DEFAULT '',
    asistentes SMALLINT NOT NULL DEFAULT 0,
    epp_ok TINYINT(1) NOT NULL DEFAULT 0,
    incidente_id INT NULL,
    FOREIGN KEY (reporte_id) REFERENCES reportes_diarios(id) ON DELETE CASCADE,
    FOREIGN KEY (incidente_id) REFERENCES seguridad_incidentes(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS reporte_fotos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reporte_id INT NOT NULL,
    archivo VARCHAR(255) NOT NULL,
    actividad_id INT NULL,
    leyenda VARCHAR(255) NOT NULL DEFAULT '',
    lat DECIMAL(10,7) NULL,
    lng DECIMAL(10,7) NULL,
    tomada_en DATETIME NULL,
    KEY idx_rf_reporte (reporte_id),
    FOREIGN KEY (reporte_id) REFERENCES reportes_diarios(id) ON DELETE CASCADE,
    FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS riesgos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    actividad_id INT NULL,
    titulo VARCHAR(250) NOT NULL,
    descripcion TEXT,
    categoria ENUM('tecnico','clima','suministro','seguridad','financiero','permisos','mano_obra','calidad','otro') NOT NULL DEFAULT 'tecnico',
    probabilidad TINYINT NOT NULL DEFAULT 2,
    impacto TINYINT NOT NULL DEFAULT 2,
    responsable VARCHAR(150) NOT NULL DEFAULT '',
    estrategia ENUM('mitigar','transferir','aceptar','evitar') NOT NULL DEFAULT 'mitigar',
    plan_accion TEXT,
    fecha_compromiso DATE NULL,
    estado ENUM('identificado','en_tratamiento','materializado','cerrado') NOT NULL DEFAULT 'identificado',
    restriccion_id INT NULL,
    creado_por VARCHAR(150) NOT NULL DEFAULT '',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_riesgos_proyecto (proyecto_id, estado, fecha_compromiso),
    KEY idx_riesgos_actividad (actividad_id),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE SET NULL
) ENGINE=InnoDB;

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
    fecha_pago_programada DATE NULL,
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

CREATE TABLE IF NOT EXISTS costos_contrato (
    proyecto_id INT NOT NULL PRIMARY KEY,
    monto_contrato DECIMAL(16,2) NOT NULL DEFAULT 0,
    anticipo_monto DECIMAL(16,2) NOT NULL DEFAULT 0,
    retencion_pct DECIMAL(5,2) NOT NULL DEFAULT 5,
    iva_pct DECIMAL(5,2) NOT NULL DEFAULT 16,
    dias_cobro INT NOT NULL DEFAULT 30,
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
    fecha_cobro_estimada DATE NULL,
    fecha_cobro_real DATE NULL,
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

CREATE TABLE IF NOT EXISTS transmittals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    numero VARCHAR(40) NOT NULL DEFAULT '',
    fecha DATE NOT NULL,
    de VARCHAR(150) NOT NULL DEFAULT '',
    asunto VARCHAR(250) NOT NULL DEFAULT '',
    mensaje TEXT,
    medio ENUM('correo','enlace','impreso','otro') NOT NULL DEFAULT 'correo',
    fecha_limite DATE NULL,
    estado ENUM('borrador','enviado','cerrado') NOT NULL DEFAULT 'enviado',
    usuario VARCHAR(150) NOT NULL DEFAULT '',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_tr_proyecto (proyecto_id, estado, fecha),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS transmittal_documentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transmittal_id INT NOT NULL,
    archivo_id INT NULL,
    nombre VARCHAR(255) NOT NULL DEFAULT '',
    revision VARCHAR(30) NOT NULL DEFAULT '',
    orden INT NOT NULL DEFAULT 0,
    FOREIGN KEY (transmittal_id) REFERENCES transmittals(id) ON DELETE CASCADE,
    FOREIGN KEY (archivo_id) REFERENCES archivos(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS transmittal_destinatarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transmittal_id INT NOT NULL,
    nombre VARCHAR(150) NOT NULL DEFAULT '',
    email VARCHAR(150) NOT NULL DEFAULT '',
    usuario_id INT NULL,
    token CHAR(40) NOT NULL,
    estado ENUM('pendiente','recibido','observado','rechazado') NOT NULL DEFAULT 'pendiente',
    acuse_comentario TEXT,
    acuse_nombre VARCHAR(150) NOT NULL DEFAULT '',
    acuse_fecha DATETIME NULL,
    acuse_ip VARCHAR(45) NOT NULL DEFAULT '',
    UNIQUE KEY uq_tr_token (token),
    KEY idx_tr_dest (transmittal_id, estado),
    FOREIGN KEY (transmittal_id) REFERENCES transmittals(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS calidad_ensayos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    actividad_id INT NULL,
    plan_id INT NULL,
    tipo VARCHAR(100) NOT NULL DEFAULT '',
    muestra VARCHAR(150) NOT NULL DEFAULT '',
    ubicacion VARCHAR(200) NOT NULL DEFAULT '',
    fecha_toma DATE NULL,
    fecha_resultado DATE NULL,
    laboratorio VARCHAR(150) NOT NULL DEFAULT '',
    norma VARCHAR(200) NOT NULL DEFAULT '',
    resultado VARCHAR(100) NOT NULL DEFAULT '',
    veredicto ENUM('pendiente','aprobado','rechazado') NOT NULL DEFAULT 'pendiente',
    archivo_id INT NULL,
    notas TEXT,
    usuario VARCHAR(150) NOT NULL DEFAULT '',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ens_proyecto (proyecto_id, veredicto, fecha_resultado),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE SET NULL,
    FOREIGN KEY (plan_id) REFERENCES calidad_planes(id) ON DELETE SET NULL,
    FOREIGN KEY (archivo_id) REFERENCES archivos(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS calidad_nc (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    actividad_id INT NULL,
    plan_id INT NULL,
    punto_id INT NULL,
    codigo VARCHAR(40) NOT NULL DEFAULT '',
    hallazgo TEXT,
    causa TEXT,
    accion TEXT,
    responsable VARCHAR(150) NOT NULL DEFAULT '',
    fecha_compromiso DATE NULL,
    costo DECIMAL(14,2) NOT NULL DEFAULT 0,
    estado ENUM('abierta','en_correccion','cerrada') NOT NULL DEFAULT 'abierta',
    evidencia_antes INT NULL,
    evidencia_despues INT NULL,
    movimiento_id INT NULL,
    usuario VARCHAR(150) NOT NULL DEFAULT '',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    cerrada_en DATETIME NULL,
    cerrada_por VARCHAR(150) NOT NULL DEFAULT '',
    KEY idx_nc_proyecto (proyecto_id, estado, fecha_compromiso),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE SET NULL,
    FOREIGN KEY (plan_id) REFERENCES calidad_planes(id) ON DELETE SET NULL,
    FOREIGN KEY (punto_id) REFERENCES calidad_puntos(id) ON DELETE SET NULL,
    FOREIGN KEY (evidencia_antes) REFERENCES archivos(id) ON DELETE SET NULL,
    FOREIGN KEY (evidencia_despues) REFERENCES archivos(id) ON DELETE SET NULL,
    FOREIGN KEY (movimiento_id) REFERENCES costos_movimientos(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS calidad_nc_acciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nc_id INT NOT NULL,
    fecha DATE NULL,
    accion TEXT,
    responsable VARCHAR(150) NOT NULL DEFAULT '',
    verificada TINYINT(1) NOT NULL DEFAULT 0,
    notas VARCHAR(500) NOT NULL DEFAULT '',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_nca_nc (nc_id),
    FOREIGN KEY (nc_id) REFERENCES calidad_nc(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS calidad_expedientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    partida_id INT NOT NULL,
    estado ENUM('borrador','cerrado') NOT NULL DEFAULT 'borrador',
    pdf_ruta VARCHAR(255) NOT NULL DEFAULT '',
    huella CHAR(32) NOT NULL DEFAULT '',
    cerrado_en DATETIME NULL,
    cerrado_por VARCHAR(150) NOT NULL DEFAULT '',
    actualizado TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_exp_partida (proyecto_id, partida_id),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (partida_id) REFERENCES partidas(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS seguridad_ats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    actividad_id INT NULL,
    fecha DATE NOT NULL,
    responsable VARCHAR(150) NOT NULL DEFAULT '',
    trabajo TEXT,
    peligros TEXT,
    controles TEXT,
    epp TEXT,
    participantes INT NOT NULL DEFAULT 0,
    estado ENUM('borrador','firmado','cerrado') NOT NULL DEFAULT 'borrador',
    usuario VARCHAR(150) NOT NULL DEFAULT '',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_ats_proyecto (proyecto_id, fecha, estado),
    KEY idx_ats_actividad (actividad_id, fecha),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS seguridad_ats_firmas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ats_id INT NOT NULL,
    nombre VARCHAR(150) NOT NULL DEFAULT '',
    puesto VARCHAR(100) NOT NULL DEFAULT '',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_atsf_ats (ats_id),
    FOREIGN KEY (ats_id) REFERENCES seguridad_ats(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS seguridad_acciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    incidente_id INT NOT NULL,
    fecha DATE NULL,
    accion TEXT,
    responsable VARCHAR(150) NOT NULL DEFAULT '',
    verificada TINYINT(1) NOT NULL DEFAULT 0,
    notas VARCHAR(500) NOT NULL DEFAULT '',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_sa_incidente (incidente_id),
    FOREIGN KEY (incidente_id) REFERENCES seguridad_incidentes(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS calendarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    nombre VARCHAR(120) NOT NULL DEFAULT '',
    activo TINYINT(1) NOT NULL DEFAULT 1,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_cal_proyecto (proyecto_id, activo),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS calendarios_jornada (
    id INT AUTO_INCREMENT PRIMARY KEY,
    calendario_id INT NOT NULL,
    dia_semana TINYINT NOT NULL,
    factor DECIMAL(3,2) NOT NULL DEFAULT 1.00,
    UNIQUE KEY uq_cal_jornada (calendario_id, dia_semana),
    FOREIGN KEY (calendario_id) REFERENCES calendarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS calendarios_festivos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    calendario_id INT NOT NULL,
    fecha DATE NOT NULL,
    motivo VARCHAR(200) NOT NULL DEFAULT '',
    tipo ENUM('festivo','paro_tecnico','clima','otro') NOT NULL DEFAULT 'festivo',
    UNIQUE KEY uq_cal_festivo (calendario_id, fecha),
    FOREIGN KEY (calendario_id) REFERENCES calendarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS frentes_tipos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    nombre VARCHAR(60) NOT NULL,
    orden INT NOT NULL DEFAULT 0,
    color VARCHAR(7) NOT NULL DEFAULT '#00b4d8',
    activo TINYINT(1) NOT NULL DEFAULT 1,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_frente_tipo (proyecto_id, nombre),
    KEY idx_ft_proyecto (proyecto_id, activo, orden),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS frentes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    nombre VARCHAR(150) NOT NULL DEFAULT '',
    tipo ENUM('piso','tramo','tunel','anillo','otro') NOT NULL DEFAULT 'piso',
    tipo_id INT NULL,
    orden INT NOT NULL DEFAULT 0,
    color VARCHAR(7) NOT NULL DEFAULT '#00b4d8',
    activo TINYINT(1) NOT NULL DEFAULT 1,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_frentes_proyecto (proyecto_id, activo, orden),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS portafolio_alertas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    clave VARCHAR(40) NOT NULL,
    nivel ENUM('aviso','critico') NOT NULL DEFAULT 'aviso',
    mensaje VARCHAR(255) NOT NULL,
    actualizado TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_portafolio_alerta (proyecto_id, clave),
    KEY idx_pa_actualizado (actualizado),
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;
