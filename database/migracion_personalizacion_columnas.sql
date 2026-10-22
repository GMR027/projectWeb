-- Fase 4: columnas nuevas (marca del cliente en los PDF e inicio personalizable por usuario)
ALTER TABLE proyectos ADD COLUMN marca_activa TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE proyectos ADD COLUMN marca_nombre VARCHAR(150) NULL;
ALTER TABLE proyectos ADD COLUMN marca_logo VARCHAR(100) NULL;
ALTER TABLE proyectos ADD COLUMN marca_color VARCHAR(7) NULL;
ALTER TABLE proyectos ADD COLUMN marca_color2 VARCHAR(7) NULL;
ALTER TABLE usuarios ADD COLUMN inicio_json TEXT NULL;
