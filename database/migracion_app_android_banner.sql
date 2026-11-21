-- Aviso de descarga de la app Android en /dashboard
-- android_app_en: última vez que el usuario entró desde la app (si existe, ya la tiene instalada).
-- android_banner_oculto: el usuario cerró el aviso y no se le vuelve a mostrar.
-- Es repetible: solo agrega las columnas si faltan.

SET @hay_app := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE() AND table_name = 'usuarios' AND column_name = 'android_app_en'
);
SET @sql_app := IF(@hay_app = 0,
  'ALTER TABLE usuarios ADD COLUMN android_app_en DATETIME NULL AFTER ultimo_acceso',
  'SELECT 1');
PREPARE stmt_app FROM @sql_app;
EXECUTE stmt_app;
DEALLOCATE PREPARE stmt_app;

SET @hay_oculto := (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE() AND table_name = 'usuarios' AND column_name = 'android_banner_oculto'
);
SET @sql_oculto := IF(@hay_oculto = 0,
  'ALTER TABLE usuarios ADD COLUMN android_banner_oculto TINYINT(1) NOT NULL DEFAULT 0 AFTER android_app_en',
  'SELECT 1');
PREPARE stmt_oculto FROM @sql_oculto;
EXECUTE stmt_oculto;
DEALLOCATE PREPARE stmt_oculto;
