-- Migración al cluster (2026-10-18): solo estructura, SIN datos. Aditiva: tablas nuevas (IF NOT EXISTS) y columnas/llaves nuevas.
-- Se generó comparando el esquema del cluster contra el local; no borra ni modifica filas existentes.
SET FOREIGN_KEY_CHECKS=0;
CREATE TABLE IF NOT EXISTS `analisis_materiales` (
  `id` int NOT NULL AUTO_INCREMENT,
  `proyecto_id` int NOT NULL,
  `nombre` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `guardada` tinyint(1) NOT NULL DEFAULT '0',
  `modelo` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `del_sistema` int NOT NULL DEFAULT '0',
  `de_la_ia` int NOT NULL DEFAULT '0',
  `creado` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `proyecto_id` (`proyecto_id`),
  CONSTRAINT `analisis_materiales_ibfk_1` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `analisis_materiales_hallazgos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `proyecto_id` int NOT NULL,
  `corrida_id` int NOT NULL,
  `huella` char(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `categoria` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `severidad` enum('baja','media','alta','critica') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'media',
  `origen` enum('sistema','ia') COLLATE utf8mb4_unicode_ci NOT NULL,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `materiales_json` text COLLATE utf8mb4_unicode_ci,
  `submittals_json` text COLLATE utf8mb4_unicode_ci,
  `solucion_json` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `estado` enum('pendiente','aceptada','rechazada') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendiente',
  `antes_json` text COLLATE utf8mb4_unicode_ci,
  `modelo` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `detectado` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `resuelto_por` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `resuelto_en` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_corrida_huella` (`corrida_id`,`huella`),
  KEY `idx_estado` (`proyecto_id`,`estado`),
  CONSTRAINT `analisis_materiales_hallazgos_ibfk_1` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `analisis_materiales_hallazgos_ibfk_2` FOREIGN KEY (`corrida_id`) REFERENCES `analisis_materiales` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `archivo_carpetas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `proyecto_id` int NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `creado` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_arch_carpeta` (`proyecto_id`,`nombre`),
  CONSTRAINT `archivo_carpetas_ibfk_1` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `auditoria_cambios` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `proyecto_id` int NOT NULL,
  `herramienta` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `evento` enum('aplicado','revertido','rechazado','reabierto') COLLATE utf8mb4_unicode_ci NOT NULL,
  `accion` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entidad_tipo` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `entidad_id` int DEFAULT NULL,
  `entidad_nombre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `resumen` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `antes_json` mediumtext COLLATE utf8mb4_unicode_ci,
  `despues_json` mediumtext COLLATE utf8mb4_unicode_ci,
  `referencia_id` int DEFAULT NULL,
  `modelo` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `usuario` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `creado` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_proyecto_fecha` (`proyecto_id`,`creado`),
  KEY `idx_entidad` (`proyecto_id`,`entidad_tipo`,`entidad_id`),
  CONSTRAINT `auditoria_cambios_ibfk_1` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `cambio_partidas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `orden_id` int NOT NULL,
  `proyecto_id` int NOT NULL,
  `tipo` enum('instalacion_temporal','instalacion','reubicacion','demolicion','adicion','modificacion') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'modificacion',
  `descripcion` varchar(300) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ubicacion_original` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `ubicacion_nueva` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `cantidad` decimal(12,2) DEFAULT NULL,
  `unidad` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `actividad_id` int DEFAULT NULL,
  `material_id` int DEFAULT NULL,
  `requiere_retiro` tinyint(1) NOT NULL DEFAULT '0',
  `estado` enum('pendiente','ejecutado','retirado','verificado','cancelado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendiente',
  `fecha_prevista` date DEFAULT NULL,
  `fecha_ejecucion` date DEFAULT NULL,
  `fecha_retiro_prevista` date DEFAULT NULL,
  `fecha_retiro_real` date DEFAULT NULL,
  `notas` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `creado` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cp_proyecto` (`proyecto_id`,`estado`),
  KEY `orden_id` (`orden_id`),
  KEY `actividad_id` (`actividad_id`),
  KEY `material_id` (`material_id`),
  CONSTRAINT `cambio_partidas_ibfk_1` FOREIGN KEY (`orden_id`) REFERENCES `ordenes_cambio` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cambio_partidas_ibfk_2` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cambio_partidas_ibfk_3` FOREIGN KEY (`actividad_id`) REFERENCES `actividades` (`id`) ON DELETE SET NULL,
  CONSTRAINT `cambio_partidas_ibfk_4` FOREIGN KEY (`material_id`) REFERENCES `materiales` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `clima_dias` (
  `proyecto_id` int NOT NULL,
  `fecha` date NOT NULL,
  `precip_mm` decimal(6,1) NOT NULL DEFAULT '0.0',
  `prob` int DEFAULT NULL,
  `viento_kmh` decimal(5,1) NOT NULL DEFAULT '0.0',
  `tmax` decimal(4,1) DEFAULT NULL,
  `tmin` decimal(4,1) DEFAULT NULL,
  `tipo` enum('pronostico','real') COLLATE utf8mb4_unicode_ci NOT NULL,
  `actualizado` datetime NOT NULL,
  PRIMARY KEY (`proyecto_id`,`fecha`),
  CONSTRAINT `clima_dias_ibfk_1` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `clima_proyecto` (
  `proyecto_id` int NOT NULL,
  `lugar` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `lat` decimal(8,5) NOT NULL,
  `lon` decimal(8,5) NOT NULL,
  `actualizado` datetime NOT NULL,
  PRIMARY KEY (`proyecto_id`),
  CONSTRAINT `clima_proyecto_ibfk_1` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `grabaciones` (
  `id` int NOT NULL AUTO_INCREMENT,
  `proyecto_id` int NOT NULL,
  `nombre` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `archivo` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `tamano` int unsigned NOT NULL DEFAULT '0',
  `duracion_seg` int unsigned NOT NULL DEFAULT '0',
  `transcripcion` longtext COLLATE utf8mb4_unicode_ci,
  `creado_por` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `creado` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_proyecto` (`proyecto_id`,`creado`),
  CONSTRAINT `grabaciones_ibfk_1` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `herramientas_ia` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `url` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `modelo` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `notas` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `activa` tinyint(1) NOT NULL DEFAULT '1',
  `creado` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `inconsistencias` (
  `id` int NOT NULL AUTO_INCREMENT,
  `proyecto_id` int NOT NULL,
  `corrida_id` int NOT NULL,
  `huella` char(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `categoria` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `severidad` enum('baja','media','alta','critica') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'media',
  `origen` enum('sistema','ia') COLLATE utf8mb4_unicode_ci NOT NULL,
  `titulo` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `actividades_json` text COLLATE utf8mb4_unicode_ci,
  `solucion_json` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `estado` enum('pendiente','aceptada','rechazada') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendiente',
  `antes_json` text COLLATE utf8mb4_unicode_ci,
  `modelo` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `detectado` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `resuelto_por` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `resuelto_en` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_corrida_huella` (`corrida_id`,`huella`),
  KEY `idx_estado` (`proyecto_id`,`estado`),
  CONSTRAINT `fk_inc_corrida` FOREIGN KEY (`corrida_id`) REFERENCES `inconsistencias_corridas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inconsistencias_ibfk_1` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `inconsistencias_corridas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `proyecto_id` int NOT NULL,
  `nombre` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `guardada` tinyint(1) NOT NULL DEFAULT '0',
  `modelo` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `del_sistema` int NOT NULL DEFAULT '0',
  `de_la_ia` int NOT NULL DEFAULT '0',
  `creado` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `proyecto_id` (`proyecto_id`),
  CONSTRAINT `inconsistencias_corridas_ibfk_1` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `informes_ia` (
  `id` int NOT NULL AUTO_INCREMENT,
  `proyecto_id` int NOT NULL,
  `tipo` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `titulo` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `parametros_json` text COLLATE utf8mb4_unicode_ci,
  `contenido_json` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `origen` enum('sistema','ia') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'sistema',
  `modelo` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `estado` enum('borrador','aprobado','descartado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'borrador',
  `aplicado_en` datetime DEFAULT NULL,
  `aplicado_ref` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `creado_por` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `creado` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_proyecto_tipo` (`proyecto_id`,`tipo`,`creado`),
  CONSTRAINT `informes_ia_ibfk_1` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `lineas_base` (
  `id` int NOT NULL AUTO_INCREMENT,
  `proyecto_id` int NOT NULL,
  `nombre` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `notas` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `fin_programado` date DEFAULT NULL,
  `total_actividades` int NOT NULL DEFAULT '0',
  `criticas_json` text COLLATE utf8mb4_unicode_ci,
  `creado_por` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `creado` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_lb_proyecto` (`proyecto_id`),
  CONSTRAINT `lineas_base_ibfk_1` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `lineas_base_actividades` (
  `linea_id` int NOT NULL,
  `actividad_id` int NOT NULL,
  `codigo` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `nombre` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `partida` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date NOT NULL,
  `duracion_dias` int NOT NULL DEFAULT '1',
  `progreso` tinyint unsigned NOT NULL DEFAULT '0',
  `hito` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`linea_id`,`actividad_id`),
  CONSTRAINT `lineas_base_actividades_ibfk_1` FOREIGN KEY (`linea_id`) REFERENCES `lineas_base` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `lineas_base_materiales` (
  `linea_id` int NOT NULL,
  `material_id` int NOT NULL,
  `nombre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `cantidad` decimal(12,2) DEFAULT NULL,
  `fecha_compra_prog` date DEFAULT NULL,
  `fecha_entrega_prog` date DEFAULT NULL,
  PRIMARY KEY (`linea_id`,`material_id`),
  CONSTRAINT `lineas_base_materiales_ibfk_1` FOREIGN KEY (`linea_id`) REFERENCES `lineas_base` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `mapa_avance_ajustes` (
  `proyecto_id` int NOT NULL,
  `rojo` tinyint unsigned NOT NULL DEFAULT '0',
  `amarillo` tinyint unsigned NOT NULL DEFAULT '50',
  `verde` tinyint unsigned NOT NULL DEFAULT '100',
  PRIMARY KEY (`proyecto_id`),
  CONSTRAINT `mapa_avance_ajustes_ibfk_1` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `mapa_avance_capas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `proyecto_id` int NOT NULL,
  `lamina_id` int NOT NULL,
  `capa_id` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `actividad_id` int NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_capa` (`lamina_id`,`capa_id`,`actividad_id`),
  KEY `proyecto_id` (`proyecto_id`),
  KEY `actividad_id` (`actividad_id`),
  CONSTRAINT `mapa_avance_capas_ibfk_1` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `mapa_avance_capas_ibfk_2` FOREIGN KEY (`lamina_id`) REFERENCES `vplan_laminas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `mapa_avance_capas_ibfk_3` FOREIGN KEY (`actividad_id`) REFERENCES `actividades` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `mapa_avance_instantaneas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `proyecto_id` int NOT NULL,
  `lamina_id` int NOT NULL,
  `actividad_id` int NOT NULL,
  `fecha` date NOT NULL,
  `porcentaje` decimal(5,2) NOT NULL,
  `peso` decimal(12,2) NOT NULL DEFAULT '0.00',
  `detalle_json` text COLLATE utf8mb4_unicode_ci,
  `creado` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dia` (`lamina_id`,`actividad_id`,`fecha`),
  KEY `idx_act` (`proyecto_id`,`actividad_id`,`fecha`),
  KEY `actividad_id` (`actividad_id`),
  CONSTRAINT `mapa_avance_instantaneas_ibfk_1` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `mapa_avance_instantaneas_ibfk_2` FOREIGN KEY (`lamina_id`) REFERENCES `vplan_laminas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `mapa_avance_instantaneas_ibfk_3` FOREIGN KEY (`actividad_id`) REFERENCES `actividades` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `materiales_inteligentes` (
  `proyecto_id` int NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '0',
  `actualizado` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`proyecto_id`),
  CONSTRAINT `materiales_inteligentes_ibfk_1` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `material_verificaciones` (
  `material_id` int NOT NULL,
  `proyecto_id` int NOT NULL,
  `estado` enum('aprobado','no_aprobado','revisar','sin_normativa') COLLATE utf8mb4_unicode_ci NOT NULL,
  `huella` char(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `resumen` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `info` mediumtext COLLATE utf8mb4_unicode_ci,
  `modelo` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `verificado` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`material_id`),
  KEY `idx_proyecto` (`proyecto_id`,`estado`),
  CONSTRAINT `material_verificaciones_ibfk_1` FOREIGN KEY (`material_id`) REFERENCES `materiales` (`id`) ON DELETE CASCADE,
  CONSTRAINT `material_verificaciones_ibfk_2` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `normativa_aplicaciones` (
  `id` int NOT NULL AUTO_INCREMENT,
  `proyecto_id` int NOT NULL,
  `documento_id` int DEFAULT NULL,
  `fragmento_id` int DEFAULT NULL,
  `entidad_tipo` enum('actividad','material') COLLATE utf8mb4_unicode_ci NOT NULL,
  `entidad_id` int NOT NULL,
  `referencia` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `requisito` text COLLATE utf8mb4_unicode_ci,
  `nota` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `origen` enum('manual','csi','palabras','ia') COLLATE utf8mb4_unicode_ci NOT NULL,
  `estado` enum('propuesta','aceptada','rechazada') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'propuesta',
  `puntaje` decimal(5,2) NOT NULL DEFAULT '0.00',
  `modelo` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `resuelto_por` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `creado` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_vinculo` (`fragmento_id`,`entidad_tipo`,`entidad_id`),
  KEY `idx_entidad` (`proyecto_id`,`entidad_tipo`,`entidad_id`),
  KEY `idx_estado` (`proyecto_id`,`estado`),
  KEY `documento_id` (`documento_id`),
  CONSTRAINT `normativa_aplicaciones_ibfk_1` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `normativa_aplicaciones_ibfk_2` FOREIGN KEY (`documento_id`) REFERENCES `normativa_documentos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `normativa_aplicaciones_ibfk_3` FOREIGN KEY (`fragmento_id`) REFERENCES `normativa_fragmentos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `normativa_documentos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `proyecto_id` int NOT NULL,
  `titulo` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo` enum('norma','especificacion','otro') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'especificacion',
  `codigo` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `idioma` enum('es','en','mixto') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'es',
  `archivo` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tamano` int unsigned NOT NULL DEFAULT '0',
  `paginas` int unsigned NOT NULL DEFAULT '0',
  `fragmentos` int unsigned NOT NULL DEFAULT '0',
  `estado` enum('indexado','sin_texto','error') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'indexado',
  `nota` varchar(300) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `creado_por` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `creado` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_proyecto` (`proyecto_id`),
  CONSTRAINT `normativa_documentos_ibfk_1` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `normativa_fragmentos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `documento_id` int NOT NULL,
  `proyecto_id` int NOT NULL,
  `pagina` int unsigned NOT NULL DEFAULT '1',
  `clausula` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `titulo` varchar(250) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `texto` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `idioma` enum('es','en') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'es',
  `csi` char(6) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_doc` (`documento_id`),
  KEY `idx_csi` (`proyecto_id`,`csi`),
  FULLTEXT KEY `ft_texto` (`titulo`,`texto`),
  CONSTRAINT `normativa_fragmentos_ibfk_1` FOREIGN KEY (`documento_id`) REFERENCES `normativa_documentos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `ordenes_cambio` (
  `id` int NOT NULL AUTO_INCREMENT,
  `proyecto_id` int NOT NULL,
  `numero` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `titulo` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `solicitante` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `estado` enum('borrador','solicitada','aprobada','rechazada','ejecutada','cerrada') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'borrador',
  `fecha` date NOT NULL,
  `impacto_costo` decimal(14,2) NOT NULL DEFAULT '0.00',
  `impacto_dias` int NOT NULL DEFAULT '0',
  `actividad_id` int DEFAULT NULL,
  `creado_por` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `creado` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_oc_proyecto` (`proyecto_id`,`estado`),
  KEY `actividad_id` (`actividad_id`),
  CONSTRAINT `ordenes_cambio_ibfk_1` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ordenes_cambio_ibfk_2` FOREIGN KEY (`actividad_id`) REFERENCES `actividades` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `permisos_trabajo` (
  `id` int NOT NULL AUTO_INCREMENT,
  `proyecto_id` int NOT NULL,
  `tipo` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `titulo` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `actividad_id` int DEFAULT NULL,
  `area` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `responsable` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `contratista` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `riesgos` text COLLATE utf8mb4_unicode_ci,
  `medidas_json` text COLLATE utf8mb4_unicode_ci,
  `vigente_desde` date NOT NULL,
  `vigente_hasta` date NOT NULL,
  `estado` enum('borrador','solicitado','aprobado','rechazado','cerrado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'solicitado',
  `origen` enum('manual','ia') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual',
  `solicitado_por` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `resuelto_por` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `resuelto_en` datetime DEFAULT NULL,
  `creado` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_proy` (`proyecto_id`,`estado`,`vigente_hasta`),
  KEY `actividad_id` (`actividad_id`),
  CONSTRAINT `permisos_trabajo_ibfk_1` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `permisos_trabajo_ibfk_2` FOREIGN KEY (`actividad_id`) REFERENCES `actividades` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `programador_propuestas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `proyecto_id` int NOT NULL,
  `modelo` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `fin_actual` date DEFAULT NULL,
  `fin_propuesto` date DEFAULT NULL,
  `resumen_json` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `filas_json` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `guardada` tinyint(1) NOT NULL DEFAULT '0',
  `nombre` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `seguimiento_json` longtext COLLATE utf8mb4_unicode_ci,
  `aceptadas_json` longtext COLLATE utf8mb4_unicode_ci,
  `creado` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `proyecto_id` (`proyecto_id`),
  CONSTRAINT `programador_propuestas_ibfk_1` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `programador_respaldos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `proyecto_id` int NOT NULL,
  `propuesta_id` int NOT NULL,
  `partida_id` int NOT NULL,
  `datos_json` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `deshecho` tinyint(1) NOT NULL DEFAULT '0',
  `creado` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `proyecto_id` (`proyecto_id`),
  CONSTRAINT `programador_respaldos_ibfk_1` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `proyecto_plantilla` (
  `proyecto_id` int NOT NULL,
  `plantilla` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `base_aplicada` tinyint(1) NOT NULL DEFAULT '0',
  `actualizado` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`proyecto_id`),
  CONSTRAINT `proyecto_plantilla_ibfk_1` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `proyecto_plantilla_historial` (
  `id` int NOT NULL AUTO_INCREMENT,
  `proyecto_id` int NOT NULL,
  `plantilla` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `origen` enum('identificado','manual') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual',
  `confianza` tinyint unsigned NOT NULL DEFAULT '0',
  `indicios` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `usuario` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `creado` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_proyecto` (`proyecto_id`,`creado`),
  CONSTRAINT `proyecto_plantilla_historial_ibfk_1` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `punch_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `proyecto_id` int NOT NULL,
  `titulo` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci,
  `actividad_id` int DEFAULT NULL,
  `ubicacion` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `categoria` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `severidad` enum('baja','media','alta','critica') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'media',
  `responsable` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `contratista` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `fecha_limite` date DEFAULT NULL,
  `estado` enum('abierto','en_proceso','corregido','cerrado','rechazado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'abierto',
  `foto_antes` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `foto_despues` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `origen` enum('manual','inspeccion','ia') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual',
  `creado_por` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `creado` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `cerrado_en` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_proy` (`proyecto_id`,`estado`),
  KEY `actividad_id` (`actividad_id`),
  CONSTRAINT `punch_items_ibfk_1` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `punch_items_ibfk_2` FOREIGN KEY (`actividad_id`) REFERENCES `actividades` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `redactor_documentos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `proyecto_id` int NOT NULL,
  `nombre` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fecha` date NOT NULL,
  `departamento` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `idioma` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'es',
  `papel` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'carta',
  `orientacion` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'vertical',
  `margen_sup` decimal(4,2) NOT NULL DEFAULT '2.54',
  `margen_inf` decimal(4,2) NOT NULL DEFAULT '2.54',
  `margen_izq` decimal(4,2) NOT NULL DEFAULT '2.54',
  `margen_der` decimal(4,2) NOT NULL DEFAULT '2.54',
  `numerar` tinyint(1) NOT NULL DEFAULT '1',
  `encabezado` tinyint(1) NOT NULL DEFAULT '0',
  `rastrear` tinyint(1) NOT NULL DEFAULT '0',
  `marca_agua` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `encabezado_texto` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `pie_texto` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `contenido` longtext COLLATE utf8mb4_unicode_ci,
  `comentarios` longtext COLLATE utf8mb4_unicode_ci,
  `creado_por` int DEFAULT NULL,
  `creado` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_proyecto` (`proyecto_id`,`departamento`),
  CONSTRAINT `redactor_documentos_ibfk_1` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `redactor_firmas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `usuario_id` int NOT NULL,
  `nombre` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `archivo` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `creado` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_usuario` (`usuario_id`),
  CONSTRAINT `redactor_firmas_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `redactor_palabras` (
  `id` int NOT NULL AUTO_INCREMENT,
  `proyecto_id` int NOT NULL,
  `palabra` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_palabra` (`proyecto_id`,`palabra`),
  CONSTRAINT `redactor_palabras_ibfk_1` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `redactor_plantillas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `proyecto_id` int NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `papel` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'carta',
  `orientacion` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'vertical',
  `margen_sup` decimal(4,2) NOT NULL DEFAULT '2.54',
  `margen_inf` decimal(4,2) NOT NULL DEFAULT '2.54',
  `margen_izq` decimal(4,2) NOT NULL DEFAULT '2.54',
  `margen_der` decimal(4,2) NOT NULL DEFAULT '2.54',
  `contenido` longtext COLLATE utf8mb4_unicode_ci,
  `creado` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `proyecto_id` (`proyecto_id`),
  CONSTRAINT `redactor_plantillas_ibfk_1` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `redactor_sellos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `documento_id` int NOT NULL,
  `usuario_id` int DEFAULT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cargo` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `huella` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `firmado` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_doc` (`documento_id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `redactor_sellos_ibfk_1` FOREIGN KEY (`documento_id`) REFERENCES `redactor_documentos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `redactor_sellos_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `redactor_versiones` (
  `id` int NOT NULL AUTO_INCREMENT,
  `documento_id` int NOT NULL,
  `etiqueta` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `usuario` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `contenido` longtext COLLATE utf8mb4_unicode_ci,
  `creado` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_documento` (`documento_id`,`creado`),
  CONSTRAINT `redactor_versiones_ibfk_1` FOREIGN KEY (`documento_id`) REFERENCES `redactor_documentos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `revisiones` (
  `id` int NOT NULL AUTO_INCREMENT,
  `proyecto_id` int NOT NULL,
  `tipo` enum('material','actividad','herramienta') COLLATE utf8mb4_unicode_ci NOT NULL,
  `ref` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `veredicto` enum('sin_evaluar','correcto','revisar','no_cumple') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'sin_evaluar',
  `revisado` tinyint(1) NOT NULL DEFAULT '0',
  `huella` char(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `nota` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `usuario_id` int DEFAULT NULL,
  `usuario` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `ejecutada_en` datetime DEFAULT NULL,
  `huella_ejecucion` char(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `actualizado` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_revision` (`proyecto_id`,`tipo`,`ref`),
  CONSTRAINT `revisiones_ibfk_1` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `revisiones_historial` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `proyecto_id` int NOT NULL,
  `tipo` enum('material','actividad','herramienta') COLLATE utf8mb4_unicode_ci NOT NULL,
  `ref` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `veredicto_antes` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'sin_evaluar',
  `veredicto_despues` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'sin_evaluar',
  `revisado_antes` tinyint(1) NOT NULL DEFAULT '0',
  `revisado_despues` tinyint(1) NOT NULL DEFAULT '0',
  `nota` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `usuario` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `creado` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_proyecto_fecha` (`proyecto_id`,`creado`),
  KEY `idx_elemento` (`proyecto_id`,`tipo`,`ref`),
  CONSTRAINT `revisiones_historial_ibfk_1` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `seguridad_incidentes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `proyecto_id` int NOT NULL,
  `fecha` date NOT NULL,
  `tipo` enum('incidente','casi_accidente','acto_inseguro','condicion_insegura') COLLATE utf8mb4_unicode_ci NOT NULL,
  `severidad` enum('baja','media','alta','critica') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'media',
  `area` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `actividad_id` int DEFAULT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `acciones` text COLLATE utf8mb4_unicode_ci,
  `estado` enum('abierto','cerrado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'abierto',
  `reportado_por` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `creado` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_proy` (`proyecto_id`,`fecha`),
  KEY `actividad_id` (`actividad_id`),
  CONSTRAINT `seguridad_incidentes_ibfk_1` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `seguridad_incidentes_ibfk_2` FOREIGN KEY (`actividad_id`) REFERENCES `actividades` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `vigilancia_estado` (
  `proyecto_id` int NOT NULL,
  `huellas_json` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `metricas_json` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `nuevos_json` longtext COLLATE utf8mb4_unicode_ci,
  `revisado` datetime NOT NULL,
  PRIMARY KEY (`proyecto_id`),
  CONSTRAINT `vigilancia_estado_ibfk_1` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- ===== Columnas y llaves nuevas en tablas existentes =====
ALTER TABLE archivos ADD COLUMN carpeta_id INT NULL;
ALTER TABLE archivos ADD CONSTRAINT fk_archivos_carpeta FOREIGN KEY (carpeta_id) REFERENCES archivo_carpetas(id) ON DELETE SET NULL;
ALTER TABLE editor_graficos ADD COLUMN tipo VARCHAR(12) NOT NULL DEFAULT 'grafico' AFTER nombre;
ALTER TABLE vplan_laminas ADD COLUMN simbologia_grafico_id INT NULL;
ALTER TABLE vplan_laminas ADD CONSTRAINT fk_lamina_simbologia FOREIGN KEY (simbologia_grafico_id) REFERENCES editor_graficos(id) ON DELETE SET NULL;
SET FOREIGN_KEY_CHECKS=1;
