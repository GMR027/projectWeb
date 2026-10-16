-- ============================================================
-- Base de datos: project
-- Sistema de Programas de Obra
-- ============================================================

CREATE DATABASE IF NOT EXISTS project CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE project;

-- ============================================================
-- USUARIOS Y AUTENTICACIÓN
-- ============================================================
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    rol ENUM('admin', 'cliente', 'visitante') NOT NULL DEFAULT 'visitante',
    logo VARCHAR(100) DEFAULT NULL,
    limite_proyectos INT NOT NULL DEFAULT 1,   -- proyectos que puede crear un cliente
    limite_almacenamiento_mb INT NOT NULL DEFAULT 300,   -- MB por proyecto de un cliente
    creado_por INT DEFAULT NULL,   -- cliente que creó a este visitante (máx. 2 por cliente)
    herramientas TEXT NULL,        -- visitante: claves de las herramientas que ve (JSON); NULL = todas
    token VARCHAR(100) DEFAULT NULL,
    confirmado TINYINT(1) DEFAULT 0,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (creado_por) REFERENCES usuarios(id) ON DELETE CASCADE
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
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
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
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    UNIQUE KEY uq_partida_codigo (proyecto_id, codigo)
) ENGINE=InnoDB;

-- ============================================================
-- ACTIVIDADES
-- ============================================================
CREATE TABLE actividades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    partida_id INT NOT NULL,
    codigo VARCHAR(50) NOT NULL,
    nombre VARCHAR(200) NOT NULL,
    descripcion TEXT,
    fecha_inicio DATE NOT NULL,
    duracion_dias INT NOT NULL DEFAULT 1,
    fecha_fin DATE NOT NULL,
    progreso TINYINT UNSIGNED DEFAULT 0,
    prioridad ENUM('baja', 'media', 'alta', 'critica') DEFAULT 'media',
    status ENUM('no_iniciada', 'en_proceso', 'completada') DEFAULT 'no_iniciada',
    pta TINYINT(1) NOT NULL DEFAULT 1, -- 4WLA: 1 = OK, 0 = pendiente (!)
    hito TINYINT(1) NOT NULL DEFAULT 0, -- 1 = hito (duración 0, inicio = fin)
    sin_material TINYINT(1) NOT NULL DEFAULT 0, -- 1 = la actividad no requiere material
    fecha_fija TINYINT(1) NOT NULL DEFAULT 0,   -- 1 = el modo automático de dependencias no la mueve
    orden INT DEFAULT 0,
    FOREIGN KEY (partida_id) REFERENCES partidas(id) ON DELETE CASCADE,
    UNIQUE KEY uq_actividad_codigo (partida_id, codigo)
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
    tipo_id INT NOT NULL,
    codigo VARCHAR(50) DEFAULT NULL,
    descripcion TEXT,
    fecha DATE DEFAULT NULL,
    estado ENUM('activa', 'resuelta', 'cancelada') DEFAULT 'activa',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE CASCADE,
    FOREIGN KEY (tipo_id) REFERENCES tipos_restriccion(id)
) ENGINE=InnoDB;

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
-- Usuario admin por defecto (password: admin123 - CAMBIAR EN PRODUCCIÓN)
INSERT INTO usuarios (nombre, email, password, rol, confirmado) VALUES
('Administrador', 'admin@po.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 1);

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
    unidad VARCHAR(30) NOT NULL DEFAULT '',
    compra ENUM('pendiente','comprado') NOT NULL DEFAULT 'pendiente',
    fecha_compra_prog DATE DEFAULT NULL,
    fecha_compra_real DATE DEFAULT NULL,
    fecha_entrega_prog DATE DEFAULT NULL,
    fecha_entrega_real DATE DEFAULT NULL,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Materiales que usa cada actividad (Planificador)
CREATE TABLE actividad_materiales (
    actividad_id INT NOT NULL,
    material_id INT NOT NULL,
    PRIMARY KEY (actividad_id, material_id),
    FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE CASCADE,
    FOREIGN KEY (material_id) REFERENCES materiales(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE msrs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    descripcion VARCHAR(500) NOT NULL DEFAULT '',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE msr_partidas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    msr_id INT NOT NULL,
    titulo VARCHAR(150) NOT NULL,
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
    FOREIGN KEY (msr_id) REFERENCES msrs(id) ON DELETE CASCADE,
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
CREATE TABLE vplan_laminas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    partida_id INT DEFAULT NULL,
    titulo VARCHAR(200) NOT NULL,
    supervisor VARCHAR(200) NOT NULL DEFAULT '',
    recovery_cols TINYINT NOT NULL DEFAULT 0,
    restr_auto TINYINT NOT NULL DEFAULT 0,
    plano VARCHAR(100) DEFAULT NULL,
    simbologia VARCHAR(100) DEFAULT NULL,
    marcas_json LONGTEXT,
    marcas_png VARCHAR(100) DEFAULT NULL,
    marcas_estados_hash VARCHAR(64) DEFAULT NULL,
    orden INT NOT NULL DEFAULT 0,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (partida_id) REFERENCES partidas(id) ON DELETE SET NULL
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

CREATE TABLE vplan_tablas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    titulo VARCHAR(200) NOT NULL,
    nombre_interno VARCHAR(150) DEFAULT NULL,   -- nombre en el catálogo (el título es el que se imprime)
    tipo ENUM('punch','restricciones','secundarias','otro') NOT NULL DEFAULT 'punch',
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
    imagen VARCHAR(100) DEFAULT NULL,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE reportes_diarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    fecha DATE NOT NULL,
    area VARCHAR(150) NOT NULL DEFAULT '',
    comentarios TEXT,
    personal_json LONGTEXT,      -- [id de personal, ...]
    actividades_json LONGTEXT,   -- [id de actividad del programa, ...]
    fotos_json LONGTEXT,         -- [nombre de archivo, ...]
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE bitacora (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    fecha DATE NOT NULL,
    departamento VARCHAR(100) NOT NULL DEFAULT '',
    descripcion TEXT NOT NULL,
    importancia ENUM('baja','media','alta','critica') NOT NULL DEFAULT 'media',
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE
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

CREATE TABLE archivos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    categoria_id INT DEFAULT NULL,
    fecha DATE NOT NULL,
    nombre VARCHAR(200) NOT NULL,
    numero_revision VARCHAR(30) NOT NULL DEFAULT '',
    archivo_original VARCHAR(255) NOT NULL,   -- nombre con el que se subió (se usa al descargar)
    archivo_guardado VARCHAR(100) NOT NULL,   -- nombre interno en storage/archivos/{proyecto}/
    tamano INT UNSIGNED NOT NULL DEFAULT 0,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (categoria_id) REFERENCES archivo_categorias(id) ON DELETE SET NULL
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

-- Evidencias fotográficas por actividad (ver database/migracion_evidencias.sql)
CREATE TABLE evidencias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proyecto_id INT NOT NULL,
    actividad_id INT DEFAULT NULL,
    fecha DATE NOT NULL,
    descripcion VARCHAR(300) NOT NULL DEFAULT '',
    foto VARCHAR(100) NOT NULL,
    usuario_id INT DEFAULT NULL,
    creado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (proyecto_id) REFERENCES proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE SET NULL
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
