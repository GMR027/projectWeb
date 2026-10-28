-- Índice para encontrar rápido los borradores de reportes diarios (y los informes por estado).
-- Idempotente: se puede aplicar en caliente.
--   mysql -u root project < database/migracion_ia_rendimiento.sql

DROP PROCEDURE IF EXISTS pw_crear_indice_ia;
DELIMITER //
CREATE PROCEDURE pw_crear_indice_ia(IN p_tabla VARCHAR(64), IN p_indice VARCHAR(64), IN p_columnas VARCHAR(255))
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

CALL pw_crear_indice_ia('informes_ia', 'idx_informes_borrador', 'proyecto_id, tipo, estado, aplicado_en');

DROP PROCEDURE IF EXISTS pw_crear_indice_ia;
