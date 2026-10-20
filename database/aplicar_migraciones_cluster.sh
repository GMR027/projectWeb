#!/usr/bin/env bash
# Aplica en el clúster (namespace projectweb) las migraciones pendientes y sube los instaladores de la app de escritorio.
# Es repetible: solo agrega lo que falta. No borra ni sobrescribe datos.
set -euo pipefail
cd "$(dirname "$0")"
NS=projectweb
sql() { kubectl -n $NS exec -i deploy/mysql -c mysql -- sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" --default-character-set=utf8mb4 -N project' 2>&1 | grep -v 'Using a password' || true; }

# Tablas nuevas (CREATE TABLE IF NOT EXISTS)
for f in migracion_estampas migracion_ensamble_pdf migracion_lookahead_config; do echo ">> $f"; sql < $f.sql; done

# Columnas nuevas: se agregan solo si no existen
col() {   # tabla columna "ALTER completo"
  local n; n=$(echo "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema='project' AND table_name='$1' AND column_name='$2';" | sql | tail -1)
  if [ "$n" = "0" ]; then echo ">> $1.$2"; echo "$3" | sql; else echo "ok  $1.$2 ya existe"; fi
}
col minutas departamento "ALTER TABLE minutas ADD COLUMN departamento VARCHAR(100) NOT NULL DEFAULT '' AFTER importancia;"
col msrs columnas_json "ALTER TABLE msrs ADD COLUMN columnas_json TEXT NULL AFTER descripcion;"
col msr_items extras_json "ALTER TABLE msr_items ADD COLUMN extras_json TEXT NULL;"
col msr_partidas color "ALTER TABLE msr_partidas ADD COLUMN color VARCHAR(7) NOT NULL DEFAULT '' AFTER titulo;"
col msrs encabezado_id "ALTER TABLE msrs ADD COLUMN encabezado_id INT NULL AFTER columnas_json;"

# Instaladores de la app de escritorio (Ajustes → App de escritorio)
R=../desktop/release
POD=$(kubectl -n $NS get pod -o name | grep 'pod/projectweb-' | head -1 | sed 's#pod/##')
for a in projectweb-desktop-linux-amd64.deb projectweb-desktop-linux-x86_64.AppImage latest-linux.yml; do
  echo ">> subiendo $a"; kubectl -n $NS cp "$R/$a" "$POD:/var/www/html/storage/instaladores/$a" -c app
done
kubectl -n $NS exec "$POD" -c app -- chown -R www-data:www-data /var/www/html/storage/instaladores
echo ">> Listo"
