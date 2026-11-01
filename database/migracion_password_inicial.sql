-- Cambio obligatorio de la contraseña inicial (la del seed es pública: admin123) y fecha del último acceso.
-- Idempotente: se puede aplicar en caliente sobre una base existente.
--
--   mysql -u root project < database/migracion_password_inicial.sql

DROP PROCEDURE IF EXISTS pw_columna_usuarios;
DELIMITER //
CREATE PROCEDURE pw_columna_usuarios(IN p_col VARCHAR(64), IN p_def VARCHAR(255))
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = 'usuarios' AND column_name = p_col
    ) THEN
        SET @pw_sql = CONCAT('ALTER TABLE usuarios ADD COLUMN `', p_col, '` ', p_def);
        PREPARE pw_st FROM @pw_sql;
        EXECUTE pw_st;
        DEALLOCATE PREPARE pw_st;
    END IF;
END //
DELIMITER ;

CALL pw_columna_usuarios('debe_cambiar_password', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER confirmado');
CALL pw_columna_usuarios('ultimo_acceso', 'DATETIME NULL AFTER debe_cambiar_password');

DROP PROCEDURE IF EXISTS pw_columna_usuarios;

-- El administrador del seed conserva la contraseña documentada hasta que la cambie.
UPDATE usuarios SET debe_cambiar_password = 1
WHERE email = 'admin@po.local' AND debe_cambiar_password = 0;
