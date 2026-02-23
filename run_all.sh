#!/usr/bin/env bash

set -euo pipefail

BASE_URL="${BASE_URL:-http://127.0.0.1:8080}"

if ! curl -fsS "${BASE_URL}/health" >/dev/null; then
  echo "Server non raggiungibile su ${BASE_URL}."
  echo "Avvia prima l'API con: php -S 0.0.0.0:8080 -t public"
  exit 1
fi

if command -v jq >/dev/null 2>&1; then
  FORMATTER="jq ."
else
  FORMATTER="cat"
fi

for i in {1..10}; do
  echo "=== Esercizio ${i} (/api/q${i}) ==="
  curl -fsS "${BASE_URL}/api/q${i}" | eval "${FORMATTER}"
  echo
done