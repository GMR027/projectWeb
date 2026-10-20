-- Carpeta (departamento) de cada minuta
ALTER TABLE minutas ADD COLUMN departamento VARCHAR(100) NOT NULL DEFAULT '' AFTER importancia;
