#!/usr/bin/env bash
# Восстановление legacy БД из dev/theins_prod_09112025_tar (формат: tar с pg_dump -Fd).
# Требует запущенный legacy_db и свободное место в Docker (~ размер архива для распаковки).
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
COMPOSE_FILE="$SCRIPT_DIR/docker-compose.dev.xml"
DUMP_TAR="$SCRIPT_DIR/theins_prod_09112025_tar"

if [[ ! -f "$DUMP_TAR" ]]; then
  echo "Нет файла бэкапа: $DUMP_TAR" >&2
  exit 1
fi

cd "$SCRIPT_DIR"

echo "Проверка legacy_db..."
docker compose -f "$COMPOSE_FILE" exec -T legacy_db true

echo "Распаковка tar → /tmp/pgdump и pg_restore (долго при большом дампе)..."
docker compose -f "$COMPOSE_FILE" exec -T legacy_db bash -s <<'EOS'
set -euo pipefail
if [[ ! -r /backup/legacy.dump.tar ]]; then
  echo "В контейнере нет /backup/legacy.dump.tar — проверьте volume в docker-compose.dev.xml" >&2
  exit 1
fi
rm -rf /tmp/pgdump
mkdir -p /tmp/pgdump
tar -xf /backup/legacy.dump.tar -C /tmp/pgdump
if [[ ! -f /tmp/pgdump/toc.dat ]]; then
  echo "После распаковки нет toc.dat — ожидался каталог формата pg_dump -Fd" >&2
  exit 1
fi
export PGPASSWORD="${POSTGRES_PASSWORD:-}"
# --clean --if-exists: можно перезапускать поверх частично заполненной БД
pg_restore \
  -h localhost -U "${POSTGRES_USER}" -d "${POSTGRES_DB}" \
  --verbose --no-owner --no-acl \
  --clean --if-exists \
  -j 4 \
  /tmp/pgdump
rm -rf /tmp/pgdump
echo "Готово."
EOS
