#!/usr/bin/env bash
# Выкатка на прод: подтянуть код и пересобрать стек. Запускать на сервере из
# каталога проекта. Идемпотентно.
set -euo pipefail

cd "$(dirname "$0")"

echo "→ git pull"
git pull --ff-only origin main

echo "→ docker compose up (prod)"
docker compose -f docker-compose.prod.yml up -d --build

echo "→ статус"
docker compose -f docker-compose.prod.yml ps

echo "✓ Готово."
