-- Migración al cluster (2026-10-15): pone la BD de producción al día con todos los cambios recientes SIN borrar datos.
-- Generada desde el esquema local; es aditiva (tablas y columnas nuevas) y se puede revisar antes de aplicar.
SET FOREIGN_KEY_CHECKS=0;

-- ===== Tablas nuevas =====
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `actividad_avance` (
  `actividad_id` int NOT NULL,
  `fecha` date NOT NULL,
  `progreso` tinyint unsigned NOT NULL,
  PRIMARY KEY (`actividad_id`,`fecha`),
  CONSTRAINT `actividad_avance_ibfk_1` FOREIGN KEY (`actividad_id`) REFERENCES `actividades` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `actividad_dependencias` (
  `id` int NOT NULL AUTO_INCREMENT,
  `proyecto_id` int NOT NULL,
  `predecesora_id` int NOT NULL,
  `sucesora_id` int NOT NULL,
  `tipo` enum('FS','SS','FF','SF') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'FS',
  `retraso` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dependencia` (`predecesora_id`,`sucesora_id`),
  KEY `proyecto_id` (`proyecto_id`),
  KEY `sucesora_id` (`sucesora_id`),
  CONSTRAINT `actividad_dependencias_ibfk_1` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `actividad_dependencias_ibfk_2` FOREIGN KEY (`predecesora_id`) REFERENCES `actividades` (`id`) ON DELETE CASCADE,
  CONSTRAINT `actividad_dependencias_ibfk_3` FOREIGN KEY (`sucesora_id`) REFERENCES `actividades` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `cajetines` (
  `id` int NOT NULL AUTO_INCREMENT,
  `proyecto_id` int NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `orientacion` enum('vertical','banda') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'vertical',
  `ancho` tinyint unsigned NOT NULL DEFAULT '28',
  `filas_json` text COLLATE utf8mb4_unicode_ci,
  `creado` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `proyecto_id` (`proyecto_id`),
  CONSTRAINT `cajetines_ibfk_1` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `curva_s_manual` (
  `proyecto_id` int NOT NULL,
  `fecha` date NOT NULL,
  `avance` decimal(5,2) NOT NULL,
  PRIMARY KEY (`proyecto_id`,`fecha`),
  CONSTRAINT `curva_s_manual_ibfk_1` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `dependencias_cambios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `proyecto_id` int NOT NULL,
  `creado` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `cambios_json` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `proyecto_id` (`proyecto_id`),
  CONSTRAINT `dependencias_cambios_ibfk_1` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `editor_categorias` (
  `id` int NOT NULL AUTO_INCREMENT,
  `proyecto_id` int NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `creado` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `proyecto_id` (`proyecto_id`),
  CONSTRAINT `editor_categorias_ibfk_1` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `editor_graficos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `proyecto_id` int NOT NULL,
  `nombre` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `categoria_id` int DEFAULT NULL,
  `ancho` int NOT NULL DEFAULT '1920',
  `alto` int NOT NULL DEFAULT '1080',
  `fondo` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'liso',
  `fondo_color` varchar(7) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#ffffff',
  `fondo_imagen` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fondo_config` text COLLATE utf8mb4_unicode_ci,
  `creado` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `proyecto_id` (`proyecto_id`),
  KEY `fk_grafico_categoria` (`categoria_id`),
  CONSTRAINT `editor_graficos_ibfk_1` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_grafico_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `editor_categorias` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `editor_laminas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `grafico_id` int NOT NULL,
  `orden` int NOT NULL DEFAULT '1',
  `nombre` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `marcas_json` longtext COLLATE utf8mb4_unicode_ci,
  `marcas_png` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `creado` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `grafico_id` (`grafico_id`),
  CONSTRAINT `editor_laminas_ibfk_1` FOREIGN KEY (`grafico_id`) REFERENCES `editor_graficos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `evidencias` (
  `id` int NOT NULL AUTO_INCREMENT,
  `proyecto_id` int NOT NULL,
  `actividad_id` int DEFAULT NULL,
  `fecha` date NOT NULL,
  `descripcion` varchar(300) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `foto` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `usuario_id` int DEFAULT NULL,
  `creado` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `proyecto_id` (`proyecto_id`),
  KEY `actividad_id` (`actividad_id`),
  CONSTRAINT `evidencias_ibfk_1` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `evidencias_ibfk_2` FOREIGN KEY (`actividad_id`) REFERENCES `actividades` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `simbolos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `proyecto_id` int NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `categoria` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `archivo` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `creado` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `proyecto_id` (`proyecto_id`),
  CONSTRAINT `simbolos_ibfk_1` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `soporte_respuesta_fotos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `respuesta_id` int NOT NULL,
  `archivo` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `respuesta_id` (`respuesta_id`),
  CONSTRAINT `soporte_respuesta_fotos_ibfk_1` FOREIGN KEY (`respuesta_id`) REFERENCES `soporte_respuestas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `soporte_respuestas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `soporte_id` int NOT NULL,
  `titulo` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `fecha` datetime NOT NULL,
  `visto` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `soporte_id` (`soporte_id`),
  CONSTRAINT `soporte_respuestas_ibfk_1` FOREIGN KEY (`soporte_id`) REFERENCES `soportes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `view360_graficos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `proyecto_id` int NOT NULL,
  `fecha` date NOT NULL,
  `nombre` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `version` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `grafico_id` int DEFAULT NULL,
  `creado` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `proyecto_id` (`proyecto_id`),
  KEY `fk_view360_grafico` (`grafico_id`),
  CONSTRAINT `fk_view360_grafico` FOREIGN KEY (`grafico_id`) REFERENCES `editor_graficos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `view360_graficos_ibfk_1` FOREIGN KEY (`proyecto_id`) REFERENCES `proyectos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `view360_notas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `view360_id` int NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `fecha` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `view360_id` (`view360_id`),
  CONSTRAINT `view360_notas_ibfk_1` FOREIGN KEY (`view360_id`) REFERENCES `view360_graficos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `vplan_lamina_actividades` (
  `lamina_id` int NOT NULL,
  `actividad_id` int NOT NULL,
  PRIMARY KEY (`lamina_id`,`actividad_id`),
  KEY `actividad_id` (`actividad_id`),
  CONSTRAINT `vplan_lamina_actividades_ibfk_1` FOREIGN KEY (`lamina_id`) REFERENCES `vplan_laminas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `vplan_lamina_actividades_ibfk_2` FOREIGN KEY (`actividad_id`) REFERENCES `actividades` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `vplan_notas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `lamina_id` int NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `fecha` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `lamina_id` (`lamina_id`),
  CONSTRAINT `vplan_notas_ibfk_1` FOREIGN KEY (`lamina_id`) REFERENCES `vplan_laminas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

-- ===== Columnas nuevas en tablas existentes =====
ALTER TABLE actividades ADD COLUMN fecha_fija TINYINT(1) NOT NULL DEFAULT 0 AFTER orden;
ALTER TABLE materiales
  ADD COLUMN nombre_base VARCHAR(255) DEFAULT NULL AFTER referencia,
  ADD COLUMN marca VARCHAR(100) NOT NULL DEFAULT '' AFTER nombre_base,
  ADD COLUMN modelo VARCHAR(100) NOT NULL DEFAULT '' AFTER marca,
  ADD COLUMN valor_extra VARCHAR(150) NOT NULL DEFAULT '' AFTER modelo;
ALTER TABLE proyectos ADD COLUMN modo_dependencias ENUM('apagado','informativo','automatico') NOT NULL DEFAULT 'apagado' AFTER creado;
ALTER TABLE usuarios ADD COLUMN herramientas TEXT DEFAULT NULL AFTER creado_por;

-- ===== Datos derivados de lo que ya existe =====
UPDATE materiales SET nombre_base = nombre WHERE nombre_base IS NULL;
INSERT IGNORE INTO actividad_avance (actividad_id, fecha, progreso)
SELECT id, CASE WHEN progreso = 100 AND fecha_fin < CURDATE() THEN fecha_fin ELSE CURDATE() END, progreso FROM actividades WHERE progreso > 0;

SET FOREIGN_KEY_CHECKS=1;
