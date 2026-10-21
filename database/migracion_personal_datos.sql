-- Personal: datos adicionales (se guardan junto al contacto en personal_contacto)
ALTER TABLE personal_contacto
    ADD COLUMN direccion VARCHAR(250) NOT NULL DEFAULT '',
    ADD COLUMN rfc VARCHAR(13) NOT NULL DEFAULT '',
    ADD COLUMN tipo VARCHAR(20) NOT NULL DEFAULT '',   -- directivo | administrativo | operativo
    ADD COLUMN notas TEXT NULL;
