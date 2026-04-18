#!/usr/bin/env bash
# Runs php artisan inside backend container and prints wall-clock duration to stderr.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

SECONDS=0
code=0
./dev.sh exec backend php artisan "$@" || code=$?

echo "" >&2
echo "Elapsed: ${SECONDS}s" >&2
exit "$code"
