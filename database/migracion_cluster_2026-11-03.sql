-- ============================================================
-- Migración de esquema para el clúster (sin datos)
-- Generada comparando schema.sql local con la BD del clúster
-- No inserta, actualiza ni borra filas.
-- ============================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

-- 1) Tablas nuevas (en orden de dependencias)
CREATE TABLE IF NOT EXISTS `almacenes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `proyecto_id` int NOT NULL,
  `nombre` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Almacén principal',
  `ubicacion` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `principal` tinyint(1) NOT NULL DEFAULT '1',
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `creado` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_alm_proyecto` (`proyecto_id`,`activo`),
  CONSTRAINT `almacenes_ibfk_1` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `salidas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `proyecto_id` int NOT NULL,
  `almacen_id` int NOT NULL,
  `actividad_id` int NOT NULL,
  `fecha` date NOT NULL,
  `recibio` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `notas` text COLLATE utf8mb4_unicode_ci,
  `usuario` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `creado` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sal_proyecto` (`proyecto_id`,`fecha`),
  KEY `idx_sal_actividad` (`actividad_id`),
  KEY `almacen_id` (`almacen_id`),
  CONSTRAINT `salidas_ibfk_1` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `salidas_ibfk_2` FOREIGN KEY (`almacen_id`) REFERENCES `almacenes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `salidas_ibfk_3` FOREIGN KEY (`actividad_id`) REFERENCES `actividades` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `compromisos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `proyecto_id` int NOT NULL,
  `partida_id` int NOT NULL,
  `actividad_id` int DEFAULT NULL,
  `tipo` enum('orden_compra','subcontrato','servicio') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'orden_compra',
  `estado` enum('borrador','vigente','parcial','cerrado','cancelado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'vigente',
  `proveedor` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `numero` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `concepto` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `monto` decimal(16,2) NOT NULL DEFAULT '0.00',
  `fecha` date NOT NULL,
  `entrega` date DEFAULT NULL,
  `fecha_pago_programada` date DEFAULT NULL,
  `archivo_id` int DEFAULT NULL,
  `notas` text COLLATE utf8mb4_unicode_ci,
  `motivo_sobregiro` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `usuario` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `creado` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_comp_proyecto` (`proyecto_id`,`estado`),
  KEY `idx_comp_partida` (`partida_id`),
  KEY `actividad_id` (`actividad_id`),
  KEY `archivo_id` (`archivo_id`),
  CONSTRAINT `compromisos_ibfk_1` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `compromisos_ibfk_2` FOREIGN KEY (`partida_id`) REFERENCES `partidas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `compromisos_ibfk_3` FOREIGN KEY (`actividad_id`) REFERENCES `actividades` (`id`) ON DELETE SET NULL,
  CONSTRAINT `compromisos_ibfk_4` FOREIGN KEY (`archivo_id`) REFERENCES `archivos` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `recepciones` (
  `id` int NOT NULL AUTO_INCREMENT,
  `proyecto_id` int NOT NULL,
  `compromiso_id` int DEFAULT NULL,
  `almacen_id` int NOT NULL,
  `fecha` date NOT NULL,
  `factura` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `proveedor` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `notas` text COLLATE utf8mb4_unicode_ci,
  `usuario` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `creado` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rec_proyecto` (`proyecto_id`,`fecha`),
  KEY `compromiso_id` (`compromiso_id`),
  KEY `almacen_id` (`almacen_id`),
  CONSTRAINT `recepciones_ibfk_1` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `recepciones_ibfk_2` FOREIGN KEY (`compromiso_id`) REFERENCES `compromisos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `recepciones_ibfk_3` FOREIGN KEY (`almacen_id`) REFERENCES `almacenes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `almacen_movimientos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `proyecto_id` int NOT NULL,
  `almacen_id` int NOT NULL,
  `material_id` int NOT NULL,
  `tipo` enum('entrada','salida','ajuste_entrada','ajuste_salida') COLLATE utf8mb4_unicode_ci NOT NULL,
  `cantidad` decimal(14,3) NOT NULL DEFAULT '0.000',
  `costo_unitario` decimal(14,2) NOT NULL DEFAULT '0.00',
  `importe` decimal(16,2) NOT NULL DEFAULT '0.00',
  `fecha` date NOT NULL,
  `actividad_id` int DEFAULT NULL,
  `compromiso_id` int DEFAULT NULL,
  `recepcion_id` int DEFAULT NULL,
  `salida_id` int DEFAULT NULL,
  `motivo` varchar(300) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `usuario` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `creado` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_am_proyecto` (`proyecto_id`,`material_id`,`fecha`),
  KEY `idx_am_almacen` (`almacen_id`,`material_id`),
  KEY `idx_am_actividad` (`actividad_id`),
  KEY `material_id` (`material_id`),
  KEY `compromiso_id` (`compromiso_id`),
  KEY `recepcion_id` (`recepcion_id`),
  KEY `salida_id` (`salida_id`),
  CONSTRAINT `almacen_movimientos_ibfk_1` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `almacen_movimientos_ibfk_2` FOREIGN KEY (`almacen_id`) REFERENCES `almacenes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `almacen_movimientos_ibfk_3` FOREIGN KEY (`material_id`) REFERENCES `materiales` (`id`) ON DELETE CASCADE,
  CONSTRAINT `almacen_movimientos_ibfk_4` FOREIGN KEY (`actividad_id`) REFERENCES `actividades` (`id`) ON DELETE SET NULL,
  CONSTRAINT `almacen_movimientos_ibfk_5` FOREIGN KEY (`compromiso_id`) REFERENCES `compromisos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `almacen_movimientos_ibfk_6` FOREIGN KEY (`recepcion_id`) REFERENCES `recepciones` (`id`) ON DELETE CASCADE,
  CONSTRAINT `almacen_movimientos_ibfk_7` FOREIGN KEY (`salida_id`) REFERENCES `salidas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `calendarios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `proyecto_id` int NOT NULL,
  `nombre` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `creado` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cal_proyecto` (`proyecto_id`,`activo`),
  CONSTRAINT `calendarios_ibfk_1` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `calendarios_festivos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `calendario_id` int NOT NULL,
  `fecha` date NOT NULL,
  `motivo` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `tipo` enum('festivo','paro_tecnico','clima','otro') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'festivo',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cal_festivo` (`calendario_id`,`fecha`),
  CONSTRAINT `calendarios_festivos_ibfk_1` FOREIGN KEY (`calendario_id`) REFERENCES `calendarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `calendarios_jornada` (
  `id` int NOT NULL AUTO_INCREMENT,
  `calendario_id` int NOT NULL,
  `dia_semana` tinyint NOT NULL,
  `factor` decimal(3,2) NOT NULL DEFAULT '1.00',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cal_jornada` (`calendario_id`,`dia_semana`),
  CONSTRAINT `calendarios_jornada_ibfk_1` FOREIGN KEY (`calendario_id`) REFERENCES `calendarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `calidad_ensayos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `proyecto_id` int NOT NULL,
  `actividad_id` int DEFAULT NULL,
  `plan_id` int DEFAULT NULL,
  `tipo` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `muestra` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `ubicacion` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `fecha_toma` date DEFAULT NULL,
  `fecha_resultado` date DEFAULT NULL,
  `laboratorio` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `norma` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `resultado` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `veredicto` enum('pendiente','aprobado','rechazado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendiente',
  `archivo_id` int DEFAULT NULL,
  `notas` text COLLATE utf8mb4_unicode_ci,
  `usuario` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `creado` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ens_proyecto` (`proyecto_id`,`veredicto`,`fecha_resultado`),
  KEY `actividad_id` (`actividad_id`),
  KEY `plan_id` (`plan_id`),
  KEY `archivo_id` (`archivo_id`),
  CONSTRAINT `calidad_ensayos_ibfk_1` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `calidad_ensayos_ibfk_2` FOREIGN KEY (`actividad_id`) REFERENCES `actividades` (`id`) ON DELETE SET NULL,
  CONSTRAINT `calidad_ensayos_ibfk_3` FOREIGN KEY (`plan_id`) REFERENCES `calidad_planes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `calidad_ensayos_ibfk_4` FOREIGN KEY (`archivo_id`) REFERENCES `archivos` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `calidad_expedientes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `proyecto_id` int NOT NULL,
  `partida_id` int NOT NULL,
  `estado` enum('borrador','cerrado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'borrador',
  `pdf_ruta` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `huella` char(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `cerrado_en` datetime DEFAULT NULL,
  `cerrado_por` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `actualizado` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_exp_partida` (`proyecto_id`,`partida_id`),
  KEY `partida_id` (`partida_id`),
  CONSTRAINT `calidad_expedientes_ibfk_1` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `calidad_expedientes_ibfk_2` FOREIGN KEY (`partida_id`) REFERENCES `partidas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `calidad_liberaciones` (
  `id` int NOT NULL AUTO_INCREMENT,
  `proyecto_id` int NOT NULL,
  `actividad_id` int NOT NULL,
  `plan_id` int DEFAULT NULL,
  `progreso_autorizado` int NOT NULL DEFAULT '100',
  `nc_snapshot` int NOT NULL DEFAULT '0',
  `motivo` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `usuario` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `creado` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_clib_actividad` (`proyecto_id`,`actividad_id`,`id`),
  KEY `actividad_id` (`actividad_id`),
  KEY `plan_id` (`plan_id`),
  CONSTRAINT `calidad_liberaciones_ibfk_1` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `calidad_liberaciones_ibfk_2` FOREIGN KEY (`actividad_id`) REFERENCES `actividades` (`id`) ON DELETE CASCADE,
  CONSTRAINT `calidad_liberaciones_ibfk_3` FOREIGN KEY (`plan_id`) REFERENCES `calidad_planes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `calidad_nc` (
  `id` int NOT NULL AUTO_INCREMENT,
  `proyecto_id` int NOT NULL,
  `actividad_id` int DEFAULT NULL,
  `plan_id` int DEFAULT NULL,
  `punto_id` int DEFAULT NULL,
  `codigo` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `hallazgo` text COLLATE utf8mb4_unicode_ci,
  `causa` text COLLATE utf8mb4_unicode_ci,
  `accion` text COLLATE utf8mb4_unicode_ci,
  `responsable` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `fecha_compromiso` date DEFAULT NULL,
  `costo` decimal(14,2) NOT NULL DEFAULT '0.00',
  `estado` enum('abierta','en_correccion','cerrada') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'abierta',
  `evidencia_antes` int DEFAULT NULL,
  `evidencia_despues` int DEFAULT NULL,
  `movimiento_id` int DEFAULT NULL,
  `usuario` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `creado` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `cerrada_en` datetime DEFAULT NULL,
  `cerrada_por` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `idx_nc_proyecto` (`proyecto_id`,`estado`,`fecha_compromiso`),
  KEY `actividad_id` (`actividad_id`),
  KEY `plan_id` (`plan_id`),
  KEY `punto_id` (`punto_id`),
  KEY `evidencia_antes` (`evidencia_antes`),
  KEY `evidencia_despues` (`evidencia_despues`),
  KEY `movimiento_id` (`movimiento_id`),
  CONSTRAINT `calidad_nc_ibfk_1` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `calidad_nc_ibfk_2` FOREIGN KEY (`actividad_id`) REFERENCES `actividades` (`id`) ON DELETE SET NULL,
  CONSTRAINT `calidad_nc_ibfk_3` FOREIGN KEY (`plan_id`) REFERENCES `calidad_planes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `calidad_nc_ibfk_4` FOREIGN KEY (`punto_id`) REFERENCES `calidad_puntos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `calidad_nc_ibfk_5` FOREIGN KEY (`evidencia_antes`) REFERENCES `archivos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `calidad_nc_ibfk_6` FOREIGN KEY (`evidencia_despues`) REFERENCES `archivos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `calidad_nc_ibfk_7` FOREIGN KEY (`movimiento_id`) REFERENCES `costos_movimientos` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `calidad_nc_acciones` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nc_id` int NOT NULL,
  `fecha` date DEFAULT NULL,
  `accion` text COLLATE utf8mb4_unicode_ci,
  `responsable` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `verificada` tinyint(1) NOT NULL DEFAULT '0',
  `notas` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `creado` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_nca_nc` (`nc_id`),
  CONSTRAINT `calidad_nc_acciones_ibfk_1` FOREIGN KEY (`nc_id`) REFERENCES `calidad_nc` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `compra_lineas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `compromiso_id` int NOT NULL,
  `material_id` int NOT NULL,
  `cantidad` decimal(14,3) NOT NULL DEFAULT '0.000',
  `precio_unitario` decimal(14,2) NOT NULL DEFAULT '0.00',
  `importe` decimal(16,2) NOT NULL DEFAULT '0.00',
  `recibido` decimal(14,3) NOT NULL DEFAULT '0.000',
  PRIMARY KEY (`id`),
  KEY `idx_cl_compromiso` (`compromiso_id`),
  KEY `idx_cl_material` (`material_id`),
  CONSTRAINT `compra_lineas_ibfk_1` FOREIGN KEY (`compromiso_id`) REFERENCES `compromisos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `compra_lineas_ibfk_2` FOREIGN KEY (`material_id`) REFERENCES `materiales` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `compromiso_aplicaciones` (
  `id` int NOT NULL AUTO_INCREMENT,
  `compromiso_id` int NOT NULL,
  `movimiento_id` int DEFAULT NULL,
  `monto` decimal(16,2) NOT NULL DEFAULT '0.00',
  `fecha` date NOT NULL,
  `factura` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `usuario` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `creado` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ca_compromiso` (`compromiso_id`,`id`),
  KEY `idx_ca_movimiento` (`movimiento_id`),
  CONSTRAINT `compromiso_aplicaciones_ibfk_1` FOREIGN KEY (`compromiso_id`) REFERENCES `compromisos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `compromiso_aplicaciones_ibfk_2` FOREIGN KEY (`movimiento_id`) REFERENCES `costos_movimientos` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `costos_contrato` (
  `proyecto_id` int NOT NULL,
  `monto_contrato` decimal(16,2) NOT NULL DEFAULT '0.00',
  `anticipo_monto` decimal(16,2) NOT NULL DEFAULT '0.00',
  `retencion_pct` decimal(5,2) NOT NULL DEFAULT '5.00',
  `iva_pct` decimal(5,2) NOT NULL DEFAULT '16.00',
  `dias_cobro` int NOT NULL DEFAULT '30',
  `notas` text COLLATE utf8mb4_unicode_ci,
  `actualizado` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`proyecto_id`),
  CONSTRAINT `costos_contrato_ibfk_1` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `estimaciones` (
  `id` int NOT NULL AUTO_INCREMENT,
  `proyecto_id` int NOT NULL,
  `numero` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fecha` date NOT NULL,
  `periodo_desde` date DEFAULT NULL,
  `periodo_hasta` date DEFAULT NULL,
  `subtotal` decimal(16,2) NOT NULL DEFAULT '0.00',
  `iva_pct` decimal(5,2) NOT NULL DEFAULT '16.00',
  `iva` decimal(16,2) NOT NULL DEFAULT '0.00',
  `retencion_pct` decimal(5,2) NOT NULL DEFAULT '5.00',
  `retencion` decimal(16,2) NOT NULL DEFAULT '0.00',
  `anticipo_pct` decimal(5,2) NOT NULL DEFAULT '0.00',
  `amortizacion` decimal(16,2) NOT NULL DEFAULT '0.00',
  `ajuste` decimal(16,2) NOT NULL DEFAULT '0.00',
  `total_bruto` decimal(16,2) NOT NULL DEFAULT '0.00',
  `neto` decimal(16,2) NOT NULL DEFAULT '0.00',
  `estado` enum('borrador','aprobada','pagada') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'borrador',
  `movimiento_id` int DEFAULT NULL,
  `fecha_cobro_estimada` date DEFAULT NULL,
  `fecha_cobro_real` date DEFAULT NULL,
  `notas` text COLLATE utf8mb4_unicode_ci,
  `usuario` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `creado` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_est_proyecto` (`proyecto_id`,`estado`,`fecha`),
  KEY `idx_est_numero` (`proyecto_id`,`numero`),
  KEY `movimiento_id` (`movimiento_id`),
  CONSTRAINT `estimaciones_ibfk_1` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `estimaciones_ibfk_2` FOREIGN KEY (`movimiento_id`) REFERENCES `costos_movimientos` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `estimacion_partidas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `estimacion_id` int NOT NULL,
  `partida_id` int NOT NULL,
  `importe` decimal(16,2) NOT NULL DEFAULT '0.00',
  PRIMARY KEY (`id`),
  KEY `idx_ep_estimacion` (`estimacion_id`),
  KEY `idx_ep_partida` (`partida_id`),
  CONSTRAINT `estimacion_partidas_ibfk_1` FOREIGN KEY (`estimacion_id`) REFERENCES `estimaciones` (`id`) ON DELETE CASCADE,
  CONSTRAINT `estimacion_partidas_ibfk_2` FOREIGN KEY (`partida_id`) REFERENCES `partidas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `flujo_conceptos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `proyecto_id` int NOT NULL,
  `nombre` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `tipo` enum('ingreso','egreso') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'egreso',
  `monto` decimal(16,2) NOT NULL DEFAULT '0.00',
  `frecuencia` enum('semanal','quincenal','mensual') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'mensual',
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `notas` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `creado` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_flujo_proyecto` (`proyecto_id`,`activo`),
  CONSTRAINT `flujo_conceptos_ibfk_1` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `frentes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `proyecto_id` int NOT NULL,
  `nombre` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `tipo` enum('piso','tramo','tunel','anillo','otro') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'piso',
  `tipo_id` int DEFAULT NULL,
  `orden` int NOT NULL DEFAULT '0',
  `color` varchar(7) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#00b4d8',
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `creado` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_frentes_proyecto` (`proyecto_id`,`activo`,`orden`),
  CONSTRAINT `frentes_ibfk_1` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `frentes_tipos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `proyecto_id` int NOT NULL,
  `nombre` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `orden` int NOT NULL DEFAULT '0',
  `color` varchar(7) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#00b4d8',
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `creado` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_frente_tipo` (`proyecto_id`,`nombre`),
  KEY `idx_ft_proyecto` (`proyecto_id`,`activo`,`orden`),
  CONSTRAINT `frentes_tipos_ibfk_1` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `portafolio_alertas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `proyecto_id` int NOT NULL,
  `clave` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nivel` enum('aviso','critico') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'aviso',
  `mensaje` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `actualizado` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_portafolio_alerta` (`proyecto_id`,`clave`),
  KEY `idx_pa_actualizado` (`actualizado`),
  CONSTRAINT `portafolio_alertas_ibfk_1` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `reporte_equipo` (
  `id` int NOT NULL AUTO_INCREMENT,
  `reporte_id` int NOT NULL,
  `equipo_id` int DEFAULT NULL,
  `nombre` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `actividad_id` int DEFAULT NULL,
  `horas` decimal(5,2) NOT NULL DEFAULT '0.00',
  `operador` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `importe` decimal(12,2) NOT NULL DEFAULT '0.00',
  PRIMARY KEY (`id`),
  KEY `idx_re_reporte` (`reporte_id`),
  KEY `equipo_id` (`equipo_id`),
  KEY `actividad_id` (`actividad_id`),
  CONSTRAINT `reporte_equipo_ibfk_1` FOREIGN KEY (`reporte_id`) REFERENCES `reportes_diarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reporte_equipo_ibfk_2` FOREIGN KEY (`equipo_id`) REFERENCES `equipo` (`id`) ON DELETE SET NULL,
  CONSTRAINT `reporte_equipo_ibfk_3` FOREIGN KEY (`actividad_id`) REFERENCES `actividades` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `reporte_fotos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `reporte_id` int NOT NULL,
  `archivo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `actividad_id` int DEFAULT NULL,
  `leyenda` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `lat` decimal(10,7) DEFAULT NULL,
  `lng` decimal(10,7) DEFAULT NULL,
  `tomada_en` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_rf_reporte` (`reporte_id`),
  KEY `actividad_id` (`actividad_id`),
  CONSTRAINT `reporte_fotos_ibfk_1` FOREIGN KEY (`reporte_id`) REFERENCES `reportes_diarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reporte_fotos_ibfk_2` FOREIGN KEY (`actividad_id`) REFERENCES `actividades` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `reporte_materiales` (
  `id` int NOT NULL AUTO_INCREMENT,
  `reporte_id` int NOT NULL,
  `material_id` int DEFAULT NULL,
  `nombre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `actividad_id` int DEFAULT NULL,
  `cantidad` decimal(14,3) NOT NULL DEFAULT '0.000',
  `unidad` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `tipo` enum('recibido','consumido','devuelto') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'consumido',
  `proveedor` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `remision` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `idx_rm_reporte` (`reporte_id`),
  KEY `material_id` (`material_id`),
  KEY `actividad_id` (`actividad_id`),
  CONSTRAINT `reporte_materiales_ibfk_1` FOREIGN KEY (`reporte_id`) REFERENCES `reportes_diarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reporte_materiales_ibfk_2` FOREIGN KEY (`material_id`) REFERENCES `materiales` (`id`) ON DELETE SET NULL,
  CONSTRAINT `reporte_materiales_ibfk_3` FOREIGN KEY (`actividad_id`) REFERENCES `actividades` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `reporte_personal` (
  `id` int NOT NULL AUTO_INCREMENT,
  `reporte_id` int NOT NULL,
  `personal_id` int DEFAULT NULL,
  `nombre` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `actividad_id` int DEFAULT NULL,
  `horas_normal` decimal(5,2) NOT NULL DEFAULT '0.00',
  `horas_extra` decimal(5,2) NOT NULL DEFAULT '0.00',
  `cuadrilla` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `observacion` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `idx_rp_reporte` (`reporte_id`),
  KEY `personal_id` (`personal_id`),
  KEY `actividad_id` (`actividad_id`),
  CONSTRAINT `reporte_personal_ibfk_1` FOREIGN KEY (`reporte_id`) REFERENCES `reportes_diarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reporte_personal_ibfk_2` FOREIGN KEY (`personal_id`) REFERENCES `personal` (`id`) ON DELETE SET NULL,
  CONSTRAINT `reporte_personal_ibfk_3` FOREIGN KEY (`actividad_id`) REFERENCES `actividades` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `reporte_seguridad` (
  `id` int NOT NULL AUTO_INCREMENT,
  `reporte_id` int NOT NULL,
  `charla_tema` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `asistentes` smallint NOT NULL DEFAULT '0',
  `epp_ok` tinyint(1) NOT NULL DEFAULT '0',
  `incidente_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `reporte_id` (`reporte_id`),
  KEY `incidente_id` (`incidente_id`),
  CONSTRAINT `reporte_seguridad_ibfk_1` FOREIGN KEY (`reporte_id`) REFERENCES `reportes_diarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reporte_seguridad_ibfk_2` FOREIGN KEY (`incidente_id`) REFERENCES `seguridad_incidentes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `reporte_tiempos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `reporte_id` int NOT NULL,
  `actividad_id` int DEFAULT NULL,
  `horas` decimal(5,2) NOT NULL DEFAULT '0.00',
  `causa` enum('lluvia','falta_material','espera','falla_equipo','seguridad','otra') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'otra',
  `nota` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `idx_rt_reporte` (`reporte_id`),
  KEY `actividad_id` (`actividad_id`),
  CONSTRAINT `reporte_tiempos_ibfk_1` FOREIGN KEY (`reporte_id`) REFERENCES `reportes_diarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reporte_tiempos_ibfk_2` FOREIGN KEY (`actividad_id`) REFERENCES `actividades` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `riesgos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `proyecto_id` int NOT NULL,
  `actividad_id` int DEFAULT NULL,
  `titulo` varchar(250) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `categoria` enum('tecnico','clima','suministro','seguridad','financiero','permisos','mano_obra','calidad','otro') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'tecnico',
  `probabilidad` tinyint NOT NULL DEFAULT '2',
  `impacto` tinyint NOT NULL DEFAULT '2',
  `responsable` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `estrategia` enum('mitigar','transferir','aceptar','evitar') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'mitigar',
  `plan_accion` text COLLATE utf8mb4_unicode_ci,
  `fecha_compromiso` date DEFAULT NULL,
  `estado` enum('identificado','en_tratamiento','materializado','cerrado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'identificado',
  `restriccion_id` int DEFAULT NULL,
  `creado_por` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `creado` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_riesgos_proyecto` (`proyecto_id`,`estado`,`fecha_compromiso`),
  KEY `idx_riesgos_actividad` (`actividad_id`),
  CONSTRAINT `riesgos_ibfk_1` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `riesgos_ibfk_2` FOREIGN KEY (`actividad_id`) REFERENCES `actividades` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `seguridad_acciones` (
  `id` int NOT NULL AUTO_INCREMENT,
  `incidente_id` int NOT NULL,
  `fecha` date DEFAULT NULL,
  `accion` text COLLATE utf8mb4_unicode_ci,
  `responsable` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `verificada` tinyint(1) NOT NULL DEFAULT '0',
  `notas` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `creado` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sa_incidente` (`incidente_id`),
  CONSTRAINT `seguridad_acciones_ibfk_1` FOREIGN KEY (`incidente_id`) REFERENCES `seguridad_incidentes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `seguridad_ats` (
  `id` int NOT NULL AUTO_INCREMENT,
  `proyecto_id` int NOT NULL,
  `actividad_id` int DEFAULT NULL,
  `fecha` date NOT NULL,
  `responsable` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `trabajo` text COLLATE utf8mb4_unicode_ci,
  `peligros` text COLLATE utf8mb4_unicode_ci,
  `controles` text COLLATE utf8mb4_unicode_ci,
  `epp` text COLLATE utf8mb4_unicode_ci,
  `participantes` int NOT NULL DEFAULT '0',
  `estado` enum('borrador','firmado','cerrado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'borrador',
  `usuario` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `creado` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ats_proyecto` (`proyecto_id`,`fecha`,`estado`),
  KEY `idx_ats_actividad` (`actividad_id`,`fecha`),
  CONSTRAINT `seguridad_ats_ibfk_1` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `seguridad_ats_ibfk_2` FOREIGN KEY (`actividad_id`) REFERENCES `actividades` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `seguridad_ats_firmas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ats_id` int NOT NULL,
  `nombre` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `puesto` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `creado` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_atsf_ats` (`ats_id`),
  CONSTRAINT `seguridad_ats_firmas_ibfk_1` FOREIGN KEY (`ats_id`) REFERENCES `seguridad_ats` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `subcontratos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `proyecto_id` int NOT NULL,
  `partida_id` int NOT NULL,
  `compromiso_id` int DEFAULT NULL,
  `codigo` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `proveedor` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `alcance` text COLLATE utf8mb4_unicode_ci,
  `monto` decimal(16,2) NOT NULL DEFAULT '0.00',
  `retencion_pct` decimal(5,2) NOT NULL DEFAULT '10.00',
  `anticipo_pct` decimal(5,2) NOT NULL DEFAULT '0.00',
  `fecha_inicio` date DEFAULT NULL,
  `fecha_fin` date DEFAULT NULL,
  `estado` enum('borrador','vigente','cerrado','cancelado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'vigente',
  `notas` text COLLATE utf8mb4_unicode_ci,
  `usuario` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `creado` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sub_proyecto` (`proyecto_id`,`estado`),
  KEY `idx_sub_partida` (`partida_id`),
  KEY `compromiso_id` (`compromiso_id`),
  CONSTRAINT `subcontratos_ibfk_1` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `subcontratos_ibfk_2` FOREIGN KEY (`partida_id`) REFERENCES `partidas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `subcontratos_ibfk_3` FOREIGN KEY (`compromiso_id`) REFERENCES `compromisos` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `subcontrato_documentos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `subcontrato_id` int NOT NULL,
  `tipo` enum('fianza','seguro','permisos','alta_personal','otro') COLLATE utf8mb4_unicode_ci NOT NULL,
  `archivo_id` int DEFAULT NULL,
  `vencimiento` date DEFAULT NULL,
  `validado` tinyint(1) NOT NULL DEFAULT '0',
  `validado_por` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `validado_en` datetime DEFAULT NULL,
  `notas` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `actualizado` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sub_doc` (`subcontrato_id`,`tipo`),
  KEY `archivo_id` (`archivo_id`),
  CONSTRAINT `subcontrato_documentos_ibfk_1` FOREIGN KEY (`subcontrato_id`) REFERENCES `subcontratos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `subcontrato_documentos_ibfk_2` FOREIGN KEY (`archivo_id`) REFERENCES `archivos` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `subcontrato_estimaciones` (
  `id` int NOT NULL AUTO_INCREMENT,
  `subcontrato_id` int NOT NULL,
  `proyecto_id` int NOT NULL,
  `numero` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `fecha` date NOT NULL,
  `periodo_desde` date DEFAULT NULL,
  `periodo_hasta` date DEFAULT NULL,
  `subtotal` decimal(16,2) NOT NULL DEFAULT '0.00',
  `iva_pct` decimal(5,2) NOT NULL DEFAULT '16.00',
  `iva` decimal(16,2) NOT NULL DEFAULT '0.00',
  `retencion_pct` decimal(5,2) NOT NULL DEFAULT '0.00',
  `retencion` decimal(16,2) NOT NULL DEFAULT '0.00',
  `anticipo_pct` decimal(5,2) NOT NULL DEFAULT '0.00',
  `amortizacion` decimal(16,2) NOT NULL DEFAULT '0.00',
  `ajuste` decimal(16,2) NOT NULL DEFAULT '0.00',
  `total_bruto` decimal(16,2) NOT NULL DEFAULT '0.00',
  `neto` decimal(16,2) NOT NULL DEFAULT '0.00',
  `estado` enum('borrador','aprobada','pagada') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'borrador',
  `movimiento_id` int DEFAULT NULL,
  `motivo_autorizacion` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `autorizado_por` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `notas` text COLLATE utf8mb4_unicode_ci,
  `usuario` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `creado` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sube_sub` (`subcontrato_id`,`estado`,`fecha`),
  KEY `proyecto_id` (`proyecto_id`),
  KEY `movimiento_id` (`movimiento_id`),
  CONSTRAINT `subcontrato_estimaciones_ibfk_1` FOREIGN KEY (`subcontrato_id`) REFERENCES `subcontratos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `subcontrato_estimaciones_ibfk_2` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `subcontrato_estimaciones_ibfk_3` FOREIGN KEY (`movimiento_id`) REFERENCES `costos_movimientos` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `transmittals` (
  `id` int NOT NULL AUTO_INCREMENT,
  `proyecto_id` int NOT NULL,
  `numero` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `fecha` date NOT NULL,
  `de` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `asunto` varchar(250) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `mensaje` text COLLATE utf8mb4_unicode_ci,
  `medio` enum('correo','enlace','impreso','otro') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'correo',
  `fecha_limite` date DEFAULT NULL,
  `estado` enum('borrador','enviado','cerrado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'enviado',
  `usuario` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `creado` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tr_proyecto` (`proyecto_id`,`estado`,`fecha`),
  CONSTRAINT `transmittals_ibfk_1` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `transmittal_destinatarios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `transmittal_id` int NOT NULL,
  `nombre` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `email` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `usuario_id` int DEFAULT NULL,
  `token` char(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `estado` enum('pendiente','recibido','observado','rechazado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendiente',
  `acuse_comentario` text COLLATE utf8mb4_unicode_ci,
  `acuse_nombre` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `acuse_fecha` datetime DEFAULT NULL,
  `acuse_ip` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tr_token` (`token`),
  KEY `idx_tr_dest` (`transmittal_id`,`estado`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `transmittal_destinatarios_ibfk_1` FOREIGN KEY (`transmittal_id`) REFERENCES `transmittals` (`id`) ON DELETE CASCADE,
  CONSTRAINT `transmittal_destinatarios_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `transmittal_documentos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `transmittal_id` int NOT NULL,
  `archivo_id` int DEFAULT NULL,
  `nombre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `revision` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `orden` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `transmittal_id` (`transmittal_id`),
  KEY `archivo_id` (`archivo_id`),
  CONSTRAINT `transmittal_documentos_ibfk_1` FOREIGN KEY (`transmittal_id`) REFERENCES `transmittals` (`id`) ON DELETE CASCADE,
  CONSTRAINT `transmittal_documentos_ibfk_2` FOREIGN KEY (`archivo_id`) REFERENCES `archivos` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2) Columnas nuevas en tablas existentes
ALTER TABLE `actividad_cantidades`
    ADD COLUMN `precio_unitario` decimal(14,2) DEFAULT NULL;
ALTER TABLE `actividades`
    ADD COLUMN `calendario_id` int DEFAULT NULL,
    ADD COLUMN `frente_id` int DEFAULT NULL,
    ADD COLUMN `fecha_inicio_real` date DEFAULT NULL,
    ADD COLUMN `fecha_fin_real` date DEFAULT NULL,
    ADD COLUMN `duracion_restante` decimal(6,2) DEFAULT NULL,
    ADD COLUMN `restriccion_tipo` enum('ninguna','no_antes','no_despues','terminar_antes') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ninguna',
    ADD COLUMN `restriccion_fecha` date DEFAULT NULL,
    ADD COLUMN `restriccion_motivo` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '';
ALTER TABLE `bitacora`
    ADD COLUMN `reporte_id` int DEFAULT NULL;
ALTER TABLE `calidad_planes`
    ADD COLUMN `bloquea` tinyint(1) NOT NULL DEFAULT '1';
ALTER TABLE `costos_partidas`
    ADD COLUMN `presupuesto_manual` decimal(16,2) DEFAULT NULL,
    ADD COLUMN `origen` enum('manual','cantidades') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual';
ALTER TABLE `equipo_horas`
    ADD COLUMN `reporte_id` int DEFAULT NULL;
ALTER TABLE `materiales`
    ADD COLUMN `stock_minimo` decimal(14,3) NOT NULL DEFAULT '0.000',
    ADD COLUMN `costo_unitario` decimal(14,2) DEFAULT NULL;
ALTER TABLE `partidas`
    ADD COLUMN `cerrada` tinyint(1) NOT NULL DEFAULT '0',
    ADD COLUMN `cerrada_por` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    ADD COLUMN `cerrada_en` datetime DEFAULT NULL,
    ADD COLUMN `cierre_motivo` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '';
ALTER TABLE `personal`
    ADD COLUMN `tarifa_hora` decimal(12,2) NOT NULL DEFAULT '0.00';
ALTER TABLE `reportes_diarios`
    ADD COLUMN `turno` enum('dia','noche') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'dia',
    ADD COLUMN `supervisor` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    ADD COLUMN `estado` enum('borrador','cerrado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'borrador',
    ADD COLUMN `clima_precip_mm` decimal(6,1) DEFAULT NULL,
    ADD COLUMN `clima_viento_kmh` decimal(5,1) DEFAULT NULL,
    ADD COLUMN `clima_tmax` decimal(4,1) DEFAULT NULL,
    ADD COLUMN `clima_tmin` decimal(4,1) DEFAULT NULL,
    ADD COLUMN `visitas` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    ADD COLUMN `firma_nombre` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    ADD COLUMN `firma_imagen` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    ADD COLUMN `offline_uuid` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    ADD COLUMN `creado_por` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    ADD COLUMN `cerrado_en` datetime DEFAULT NULL;
ALTER TABLE `seguridad_incidentes`
    ADD COLUMN `reporte_id` int DEFAULT NULL,
    ADD COLUMN `causa_inmediata` text COLLATE utf8mb4_unicode_ci,
    ADD COLUMN `causa_raiz` text COLLATE utf8mb4_unicode_ci,
    ADD COLUMN `acciones_correctivas` text COLLATE utf8mb4_unicode_ci,
    ADD COLUMN `dias_perdidos` int NOT NULL DEFAULT '0',
    ADD COLUMN `costo` decimal(14,2) NOT NULL DEFAULT '0.00',
    ADD COLUMN `investigado_por` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    ADD COLUMN `cerrado_en` datetime DEFAULT NULL;

-- 3) Índices y llaves foráneas nuevas en tablas existentes
ALTER TABLE `actividades`
    ADD KEY `idx_act_calendario` (`calendario_id`),
    ADD KEY `idx_act_frente` (`frente_id`);
ALTER TABLE `bitacora`
    ADD KEY `idx_bit_reporte` (`reporte_id`),
    ADD CONSTRAINT `fk_bit_reporte` FOREIGN KEY (`reporte_id`) REFERENCES `reportes_diarios` (`id`) ON DELETE SET NULL;
ALTER TABLE `equipo_horas`
    ADD KEY `idx_eh_reporte` (`reporte_id`),
    ADD CONSTRAINT `fk_eh_reporte` FOREIGN KEY (`reporte_id`) REFERENCES `reportes_diarios` (`id`) ON DELETE SET NULL;
ALTER TABLE `reportes_diarios`
    ADD UNIQUE KEY `uq_reporte_offline` (`offline_uuid`);
ALTER TABLE `seguridad_incidentes`
    ADD KEY `idx_si_reporte` (`reporte_id`),
    ADD CONSTRAINT `fk_si_reporte` FOREIGN KEY (`reporte_id`) REFERENCES `reportes_diarios` (`id`) ON DELETE SET NULL;

SET FOREIGN_KEY_CHECKS=1;
