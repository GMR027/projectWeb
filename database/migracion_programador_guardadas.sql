-- Propuestas del Programador que el admin guarda: se conservan siempre (las no guardadas siguen limitadas a las 10 últimas por proyecto).
ALTER TABLE programador_propuestas ADD COLUMN guardada TINYINT(1) NOT NULL DEFAULT 0 AFTER filas_json;
ALTER TABLE programador_propuestas ADD COLUMN nombre VARCHAR(150) NOT NULL DEFAULT '' AFTER guardada;
