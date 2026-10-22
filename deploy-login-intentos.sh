#!/usr/bin/env bash
# Despliega el código nuevo y crea la tabla login_intentos SIN tocar los datos existentes.
# Uso: ./deploy-login-intentos.sh   (desde una máquina con git, kubectl, helm y acceso al clúster)
set -euo pipefail

# Ruta del proyecto: la de este script, o la que se pase con PROYECTO=/ruta
cd "${PROYECTO:-$(cd "$(dirname "$0")" && pwd)}"

NS=projectweb
MYSQL_POD=deploy/mysql

for c in git kubectl helm; do
  command -v "$c" >/dev/null 2>&1 || { echo "ERROR: falta '$c' en esta máquina"; exit 1; }
done
kubectl -n "$NS" get deploy >/dev/null 2>&1 || { echo "ERROR: no hay acceso al namespace '$NS' (revisa el kubeconfig)"; exit 1; }

echo ">>> 1/5 Actualizando el repositorio"
git pull --ff-only

echo ">>> 2/5 Verificando que llegó el código nuevo"
test -f classes/LoginLimite.php || { echo "ERROR: no llegó classes/LoginLimite.php"; exit 1; }
test -f database/migracion_login_intentos.sql || { echo "ERROR: no llegó la migración"; exit 1; }

echo ">>> 3/5 Desplegando SOLO código (sin --db ni --files: la BD no se toca)"
./deploy.sh

echo ">>> 4/5 Creando la tabla login_intentos solo si no existe"
EXISTS=$(kubectl -n "$NS" exec "$MYSQL_POD" -- sh -c \
  'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=\"project\" AND table_name=\"login_intentos\";"')
if [ "$EXISTS" = "0" ]; then
  kubectl -n "$NS" exec -i "$MYSQL_POD" -- sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" project' \
    < database/migracion_login_intentos.sql
  echo "    -> tabla creada"
else
  echo "    -> la tabla ya existía, nada que hacer"
fi

echo ">>> 5/5 Verificación final (los datos deben seguir ahí)"
TABLAS=$(kubectl -n "$NS" exec "$MYSQL_POD" -- sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" -N -e "SHOW TABLES FROM project;"' | wc -l)
echo "    tablas en project: $TABLAS"
kubectl -n "$NS" exec "$MYSQL_POD" -- sh -c \
  'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" -e "SELECT COUNT(*) AS usuarios FROM project.usuarios; SELECT COUNT(*) AS proyectos FROM project.proyectos; DESCRIBE project.login_intentos;"'
echo ">>> Listo: https://projectweb.iguzman.com.mx"
