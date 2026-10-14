#!/usr/bin/env bash
# Despliega ProjectWeb en el cluster (namespace projectweb).
#   ./deploy.sh              compila assets, construye/sube imagen y hace helm upgrade
#   ./deploy.sh --db         además REEMPLAZA la BD del cluster con tu BD local "project"
#   ./deploy.sh --files      además copia public/uploads, public/exportaciones y storage al cluster
#   ./deploy.sh --skip-build solo helm upgrade con el último tag (rollback/config)
set -euo pipefail
cd "$(dirname "$0")"

NS=projectweb
RELEASE=projectweb
IMAGE=${IMAGE:-gmr27/projectweb}
SECRET=projectweb-db
LOCAL_DB=${LOCAL_DB:-project}
LOCAL_DB_USER=${LOCAL_DB_USER:-root}

SYNC_DB=0; SYNC_FILES=0; BUILD=1
for a in "$@"; do case $a in
  --db) SYNC_DB=1;; --files) SYNC_FILES=1;; --skip-build) BUILD=0;;
  *) echo "Opción desconocida: $a"; exit 1;; esac; done

TAG=$(date +%Y%m%d-%H%M%S)
if [ $BUILD -eq 0 ]; then
  TAG=$(helm get values $RELEASE -n $NS -o json | sed -n 's/.*"tag":"\([^"]*\)".*/\1/p')
  TAG=${TAG:-latest}
fi

kubectl get ns $NS >/dev/null 2>&1 || kubectl create namespace $NS

# Secret con contraseñas de BD (se crea una sola vez)
if ! kubectl -n $NS get secret $SECRET >/dev/null 2>&1; then
  echo ">> Creando secret $SECRET"
  kubectl -n $NS create secret generic $SECRET \
    --from-literal=DB_PASSWORD="$(openssl rand -hex 16)" \
    --from-literal=MYSQL_ROOT_PASSWORD="$(openssl rand -hex 16)"
fi

if [ $BUILD -eq 1 ]; then
  echo ">> Compilando assets"
  npx gulp
  echo ">> Construyendo $IMAGE:$TAG"
  docker build -t "$IMAGE:$TAG" -t "$IMAGE:latest" .
  docker push "$IMAGE:$TAG"
  docker push "$IMAGE:latest"
fi

# La BD debe existir antes de importar: instalar mysql primero si es la primera vez
echo ">> helm upgrade"
helm upgrade --install $RELEASE helm/projectweb -n $NS \
  --set image.repository="$IMAGE" --set image.tag="$TAG" --wait --timeout 5m

if [ $SYNC_DB -eq 1 ]; then
  read -r -p "Esto SOBRESCRIBE la BD '$LOCAL_DB' del cluster con tu BD local. Escribe 'si': " ok
  [ "$ok" = "si" ] || { echo "Cancelado"; exit 1; }
  echo ">> Importando BD"
  mysqldump -u "$LOCAL_DB_USER" --single-transaction --routines --triggers --no-tablespaces \
      --default-character-set=utf8mb4 "$LOCAL_DB" \
    | kubectl -n $NS exec -i deploy/mysql -c mysql -- sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" --default-character-set=utf8mb4 project'
  kubectl -n $NS rollout restart deploy/projectweb
  kubectl -n $NS rollout status deploy/projectweb --timeout=5m
fi

if [ $SYNC_FILES -eq 1 ]; then
  echo ">> Copiando archivos"
  kubectl -n $NS rollout status deploy/projectweb --timeout=5m
  tar -c public/uploads public/exportaciones storage/archivos storage/fichas storage/import 2>/dev/null \
    | kubectl -n $NS exec -i deploy/projectweb -c app -- sh -c 'tar -x -C /var/www/html && chown -R www-data:www-data /var/www/html/public/uploads /var/www/html/public/exportaciones /var/www/html/storage'
fi

echo ">> Listo: https://projectweb.iguzman.com.mx  (imagen $IMAGE:$TAG)"
