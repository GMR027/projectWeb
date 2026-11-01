-- Colaboradores (usuarios que editan) y topes de equipo por cliente.
--
-- El visitante sigue siendo `rol = 'visitante'`: lo que cambia es su `nivel_acceso`.
--   nivel_acceso = 'ver'    → visitante de solo lectura (como hasta ahora)
--   nivel_acceso = 'editar' → colaborador: puede escribir en las herramientas de `herramientas_edicion`
--                             de los proyectos que tenga asignados. Nunca puede borrar (eso es del dueño).
--
-- `herramientas` (ya existía) es lo que ve; `herramientas_edicion` es lo que además puede editar.
-- El dueño los crea según los topes que fija el admin en su cuenta:
--   limite_visitantes (2 por defecto) y limite_colaboradores (3 por defecto).
--
-- Aplicar con: mysql -u root project < database/migracion_colaboradores.sql
-- (en el clúster, ver database/aplicar_migraciones_cluster.sh)

ALTER TABLE usuarios
    ADD COLUMN nivel_acceso ENUM('ver','editar') NOT NULL DEFAULT 'ver' AFTER rol,
    ADD COLUMN herramientas_edicion TEXT NULL AFTER herramientas,
    ADD COLUMN puede_archivos_ajenos TINYINT(1) NOT NULL DEFAULT 0 AFTER herramientas_edicion,
    ADD COLUMN limite_visitantes INT NOT NULL DEFAULT 2 AFTER creado_por,
    ADD COLUMN limite_colaboradores INT NOT NULL DEFAULT 3 AFTER limite_visitantes;

-- Los visitantes que ya existen siguen siendo de solo lectura (el DEFAULT 'ver' los cubre).
-- `creado_por` ya tenía su clave foránea; los topes nuevos no necesitan índice.

-- Quién subió cada archivo (para que un colaborador no toque los del dueño si no se le autoriza).
-- Las filas anteriores quedan en NULL = archivo del proyecto (del dueño).
ALTER TABLE archivos
    ADD COLUMN subido_por INT NULL AFTER proyecto_id,
    ADD KEY idx_archivos_subido_por (subido_por);

-- Atribución exacta en el historial: el nombre puede cambiar o repetirse entre dos personas.
-- Las filas anteriores quedan en NULL y se siguen atribuyendo por nombre.
ALTER TABLE auditoria_cambios
    ADD COLUMN usuario_id INT NULL AFTER usuario,
    ADD KEY idx_auditoria_usuario (proyecto_id, usuario_id);
