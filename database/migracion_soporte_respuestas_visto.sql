-- Aviso al cliente: 0 = respuesta del administrador que aún no ha visto (notificación en el menú y en Soporte)
ALTER TABLE soporte_respuestas ADD COLUMN visto TINYINT(1) NOT NULL DEFAULT 0 AFTER fecha;
-- Las respuestas que ya existían no se avisan como nuevas
UPDATE soporte_respuestas SET visto = 1;
