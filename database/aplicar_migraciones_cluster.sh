#!/usr/bin/env bash
# Aplica en el clúster (namespace projectweb) las migraciones pendientes y sube los instaladores de la app de escritorio.
# Es repetible: solo agrega lo que falta. No borra ni sobrescribe datos.
set -euo pipefail
cd "$(dirname "$0")"
NS=projectweb
sql() { kubectl -n $NS exec -i deploy/mysql -c mysql -- sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" --default-character-set=utf8mb4 -N project' 2>&1 | grep -v 'Using a password' || true; }

# Tablas nuevas (CREATE TABLE IF NOT EXISTS)
for f in migracion_estampas migracion_ensamble_pdf migracion_lookahead_config migracion_vplan_columnas migracion_directorio migracion_permisos_tipos migracion_ia_fase2 migracion_fase3 migracion_personalizacion migracion_fase5 migracion_login_intentos migracion_telegram migracion_telegram_config migracion_telegram_limites migracion_inconsistencias_lotes migracion_analisis_materiales migracion_submittal_laminas migracion_rfi_laminas migracion_subactividades migracion_subactividad_hojas migracion_indices_rendimiento migracion_calidad_equipo_contratos_cierre migracion_ia_rendimiento migracion_dependencias_materiales_tablas; do echo ">> $f"; sql < $f.sql; done

# Columnas nuevas: se agregan solo si no existen
col() {   # tabla columna "ALTER completo"
  local n; n=$(echo "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema='project' AND table_name='$1' AND column_name='$2';" | sql | tail -1)
  if [ "$n" = "0" ]; then echo ">> $1.$2"; echo "$3" | sql; else echo "ok  $1.$2 ya existe"; fi
}
col proyectos marca_activa "ALTER TABLE proyectos ADD COLUMN marca_activa TINYINT(1) NOT NULL DEFAULT 0;"
col proyectos marca_nombre "ALTER TABLE proyectos ADD COLUMN marca_nombre VARCHAR(150) NULL;"
col proyectos marca_logo "ALTER TABLE proyectos ADD COLUMN marca_logo VARCHAR(100) NULL;"
col proyectos marca_color "ALTER TABLE proyectos ADD COLUMN marca_color VARCHAR(7) NULL;"
col proyectos marca_color2 "ALTER TABLE proyectos ADD COLUMN marca_color2 VARCHAR(7) NULL;"
col usuarios inicio_json "ALTER TABLE usuarios ADD COLUMN inicio_json TEXT NULL;"
col minutas departamento "ALTER TABLE minutas ADD COLUMN departamento VARCHAR(100) NOT NULL DEFAULT '' AFTER importancia;"
col msrs columnas_json "ALTER TABLE msrs ADD COLUMN columnas_json TEXT NULL AFTER descripcion;"
col msr_items extras_json "ALTER TABLE msr_items ADD COLUMN extras_json TEXT NULL;"
col msr_partidas color "ALTER TABLE msr_partidas ADD COLUMN color VARCHAR(7) NOT NULL DEFAULT '' AFTER titulo;"
col msrs encabezado_id "ALTER TABLE msrs ADD COLUMN encabezado_id INT NULL AFTER columnas_json;"
col evidencias departamento "ALTER TABLE evidencias ADD COLUMN departamento VARCHAR(100) NOT NULL DEFAULT '';"
col evidencias categoria "ALTER TABLE evidencias ADD COLUMN categoria VARCHAR(100) NOT NULL DEFAULT '';"
for c in "direccion VARCHAR(250) NOT NULL DEFAULT ''" "rfc VARCHAR(13) NOT NULL DEFAULT ''" "tipo VARCHAR(20) NOT NULL DEFAULT ''" "notas TEXT NULL"; do
  col personal_contacto "${c%% *}" "ALTER TABLE personal_contacto ADD COLUMN $c;"
done
col vplan_tablas punch_config "ALTER TABLE vplan_tablas ADD COLUMN punch_config TEXT NULL;"
col permisos_trabajo nombre_permiso "ALTER TABLE permisos_trabajo ADD COLUMN nombre_permiso VARCHAR(150) NOT NULL DEFAULT '' AFTER tipo;"
col usuarios novedades_vistas "ALTER TABLE usuarios ADD COLUMN novedades_vistas DATETIME NULL;"
col proyectos registros_por_pagina "ALTER TABLE proyectos ADD COLUMN registros_por_pagina SMALLINT NOT NULL DEFAULT 10;"
col proyectos tema_modo "ALTER TABLE proyectos ADD COLUMN tema_modo ENUM('usuario','claro','oscuro') NOT NULL DEFAULT 'usuario';"
col proyectos tema_paleta "ALTER TABLE proyectos ADD COLUMN tema_paleta VARCHAR(20) NOT NULL DEFAULT 'naranja';"
col proyectos limite_almacenamiento_mb "ALTER TABLE proyectos ADD COLUMN limite_almacenamiento_mb INT NULL AFTER marca_color2;"
for c in "clima_auto TINYINT(1) NOT NULL DEFAULT 0" "clima_ultimo DATE NULL" "clima_aviso DATE NULL"; do
  col proyectos "${c%% *}" "ALTER TABLE proyectos ADD COLUMN $c;"
done
for c in "resumen_matutino TINYINT(1) NOT NULL DEFAULT 0" "resumen_ultimo DATE NULL"; do
  col proyectos "${c%% *}" "ALTER TABLE proyectos ADD COLUMN $c;"
done
for c in "programa_nombre VARCHAR(200) NULL" "programa_columnas_json TEXT NULL" "programa_indicador_json TEXT NULL"; do
  col proyectos "${c%% *}" "ALTER TABLE proyectos ADD COLUMN $c;"
done
for c in "responsable VARCHAR(150) NULL" "rec_inicio DATE NULL" "rec_fin DATE NULL" "libre_texto VARCHAR(255) NULL" "libre_numero DECIMAL(14,4) NULL" "libre_fecha DATE NULL" "icono VARCHAR(120) NULL"; do
  col actividades "${c%% *}" "ALTER TABLE actividades ADD COLUMN $c;"
done
col proyectos modo_dependencias_materiales "ALTER TABLE proyectos ADD COLUMN modo_dependencias_materiales ENUM('apagado','informativo','automatico') NOT NULL DEFAULT 'apagado';"
col actividad_materiales rol "ALTER TABLE actividad_materiales ADD COLUMN rol ENUM('instala','consume') NOT NULL DEFAULT 'instala';"
echo "ALTER TABLE vplan_tablas MODIFY COLUMN tipo ENUM('punch','restricciones','secundarias','otro','punchlist','materiales') NOT NULL DEFAULT 'punchlist';" | sql   # idempotente
col vplan_laminas materiales_tabla_id "ALTER TABLE vplan_laminas ADD COLUMN materiales_tabla_id INT NULL AFTER marcas_estados_hash;"
nk=$(echo "SELECT COUNT(*) FROM information_schema.table_constraints WHERE table_schema='project' AND table_name='vplan_laminas' AND constraint_name='fk_vplan_lamina_materiales';" | sql | tail -1)
if [ "$nk" = "0" ]; then echo ">> vplan_laminas.fk_vplan_lamina_materiales"; echo "ALTER TABLE vplan_laminas ADD CONSTRAINT fk_vplan_lamina_materiales FOREIGN KEY (materiales_tabla_id) REFERENCES vplan_tablas(id) ON DELETE SET NULL;" | sql; else echo "ok  vplan_laminas.fk_vplan_lamina_materiales ya existe"; fi

# Historial de cambios (sección Administración): origen, estado de revisión y sus eventos manuales
for c in "origen ENUM('ia','manual') NOT NULL DEFAULT 'ia' AFTER proyecto_id" "estado ENUM('pendiente','aceptado','revertido') NOT NULL DEFAULT 'pendiente' AFTER evento" "estado_en DATETIME NULL AFTER estado" "estado_por VARCHAR(150) NOT NULL DEFAULT '' AFTER estado_en"; do
  col auditoria_cambios "${c%% *}" "ALTER TABLE auditoria_cambios ADD COLUMN $c;"
done
nk=$(echo "SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema='project' AND table_name='auditoria_cambios' AND index_name='idx_proyecto_estado';" | sql | tail -1)
if [ "$nk" = "0" ]; then echo ">> auditoria_cambios.idx_proyecto_estado"; echo "ALTER TABLE auditoria_cambios ADD KEY idx_proyecto_estado (proyecto_id, estado, creado);" | sql; else echo "ok  auditoria_cambios.idx_proyecto_estado ya existe"; fi
echo "ALTER TABLE auditoria_cambios MODIFY COLUMN evento ENUM('aplicado','revertido','rechazado','reabierto','creado','modificado','eliminado') NOT NULL;" | sql   # idempotente
echo "UPDATE auditoria_cambios SET estado='aceptado', estado_en=creado, estado_por=usuario WHERE origen='ia' AND estado='pendiente';" | sql   # idempotente

# Instaladores de la app de escritorio (Ajustes → App de escritorio)
R=../desktop/release
POD=$(kubectl -n $NS get pod -o name | grep 'pod/projectweb-' | head -1 | sed 's#pod/##')
for a in projectweb-desktop-linux-amd64.deb projectweb-desktop-linux-x86_64.AppImage latest-linux.yml; do
  echo ">> subiendo $a"; kubectl -n $NS cp "$R/$a" "$POD:/var/www/html/storage/instaladores/$a" -c app
done
kubectl -n $NS exec "$POD" -c app -- chown -R www-data:www-data /var/www/html/storage/instaladores
echo ">> Listo"
