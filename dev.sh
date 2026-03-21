#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")" && pwd)"
COMPOSE_FILE="$ROOT/dev/docker-compose.dev.xml"

if [[ $# -eq 0 ]]; then
  echo "Usage: $0 <docker compose arguments>" >&2
  echo "Examples: $0 up -d   $0 down   $0 restart nginx   $0 logs -f backend   $0 ps" >&2
  exit 1
fi

exec docker compose -f "$COMPOSE_FILE" "$@"
