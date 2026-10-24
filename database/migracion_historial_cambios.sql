-- Historial de cambios del proyecto (sección Administración): además de lo que hizo la IA, registra los cambios
-- manuales de todos los módulos. Solo se agregan filas: revertir o aceptar nunca borra el registro original.
--
-- origen  = 'ia'     → lo escribió Auditoria::registrar() (herramientas de IA). Como nada cambia datos sin que el
--                      admin lo acepte, nacen en 'aceptado' y no ensucian la cola de revisión.
-- origen  = 'manual' → lo escribió HistorialCambios::registrar() (cambio hecho a mano en una herramienta). Nace
--                      'pendiente' hasta que un admin o el cliente dueño lo acepte o lo cancele (revierta).
--
-- Aplicar con: mysql -u root project < database/migracion_historial_cambios.sql
-- (en el clúster, ver database/aplicar_migraciones_cluster.sh)

ALTER TABLE auditoria_cambios
    ADD COLUMN origen ENUM('ia','manual') NOT NULL DEFAULT 'ia' AFTER proyecto_id,
    ADD COLUMN estado ENUM('pendiente','aceptado','revertido') NOT NULL DEFAULT 'pendiente' AFTER evento,
    ADD COLUMN estado_en DATETIME NULL AFTER estado,
    ADD COLUMN estado_por VARCHAR(150) NOT NULL DEFAULT '' AFTER estado_en;

-- Eventos de los cambios hechos a mano en una herramienta (los de IA se conservan tal cual)
ALTER TABLE auditoria_cambios
    MODIFY COLUMN evento ENUM('aplicado','revertido','rechazado','reabierto','creado','modificado','eliminado') NOT NULL;

-- La cola de revisión se lista por proyecto + estado y de lo más reciente a lo más antiguo
ALTER TABLE auditoria_cambios
    ADD KEY idx_proyecto_estado (proyecto_id, estado, creado);

-- Lo que ya estaba registrado eran acciones de la IA: ya fueron aceptadas por el admin, así que no entran como pendientes.
-- El filtro por origen hace que se pueda volver a ejecutar sin tocar los cambios manuales pendientes.
UPDATE auditoria_cambios SET estado = 'aceptado', estado_en = creado, estado_por = usuario
WHERE origen = 'ia' AND estado = 'pendiente';
