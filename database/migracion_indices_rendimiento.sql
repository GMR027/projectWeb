-- Índices para las consultas más repetidas de la plataforma (programa, Gantt, evidencias, reportes y bitácora).
-- Es idempotente: si el índice ya existe no hace nada. Se puede aplicar en caliente (MySQL 8 / MariaDB 10.4+).
--
--   mysql -u root project < database/migracion_indices_rendimiento.sql
--
-- Las instalaciones nuevas ya los traen en database/schema.sql.

DROP PROCEDURE IF EXISTS pw_crear_indice;
DELIMITER //
CREATE PROCEDURE pw_crear_indice(IN p_tabla VARCHAR(64), IN p_indice VARCHAR(64), IN p_columnas VARCHAR(255))
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.statistics
        WHERE table_schema = DATABASE() AND table_name = p_tabla AND index_name = p_indice
    ) THEN
        SET @pw_sql = CONCAT('ALTER TABLE `', p_tabla, '` ADD INDEX `', p_indice, '` (', p_columnas, ')');
        PREPARE pw_st FROM @pw_sql;
        EXECUTE pw_st;
        DEALLOCATE PREPARE pw_st;
    END IF;
END //
DELIMITER ;

CALL pw_crear_indice('actividades', 'idx_actividad_orden', 'partida_id, orden');
CALL pw_crear_indice('restricciones', 'idx_restriccion_estado', 'actividad_id, estado');
CALL pw_crear_indice('reportes_diarios', 'idx_reporte_proyecto_fecha', 'proyecto_id, fecha');
CALL pw_crear_indice('bitacora', 'idx_bitacora_proyecto_fecha', 'proyecto_id, fecha');
CALL pw_crear_indice('evidencias', 'idx_evidencia_proyecto_fecha', 'proyecto_id, fecha');

DROP PROCEDURE IF EXISTS pw_crear_indice;
