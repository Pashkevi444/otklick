#!/usr/bin/env bash
#
# Деплой «Отклика» на прод-сервер одной командой:
#   ./scripts/deploy.sh
#
# Что делает:
#   1) переносит свежий код на сервер (tar по SSH, без vendor/node_modules/.env/build);
#   2) БЭКАП БД (pg_dump → backups/db_<ts>.sql.gz, ротация — последние 10) ДО миграций;
#   3) пересобирает и перезапускает стек (docker compose up -d --build);
#      app-контейнер на старте сам прогоняет `php artisan migrate --force`;
#   4) показывает статус миграций, состояние контейнеров и проверяет HTTP.
#
# Бэкап делается ПЕРЕД пересборкой (миграции на старте app): если миграция
# сломает данные — есть свежий дамп для отката (gunzip | psql).
#
# Доступы к серверу НЕ хранятся в репозитории (он публичный). Их берём из
# scripts/deploy.env (gitignored) или из переменных окружения OTKLIK_DEPLOY_*:
#
#   OTKLIK_DEPLOY_HOST   — IP/хост сервера (обязательно)
#   OTKLIK_DEPLOY_USER   — пользователь SSH (по умолчанию root)
#   OTKLIK_DEPLOY_KEY    — путь к приватному ключу (по умолчанию ~/.ssh/otklick_deploy)
#   OTKLIK_DEPLOY_PATH   — каталог проекта на сервере (по умолчанию /opt/otklick)
#
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"

# Необязательный файл с доступами (не коммитится).
# shellcheck disable=SC1091
[ -f "$SCRIPT_DIR/deploy.env" ] && source "$SCRIPT_DIR/deploy.env"

HOST="${OTKLIK_DEPLOY_HOST:?Задайте OTKLIK_DEPLOY_HOST (в scripts/deploy.env или в окружении)}"
USER="${OTKLIK_DEPLOY_USER:-root}"
KEY="${OTKLIK_DEPLOY_KEY:-$HOME/.ssh/otklick_deploy}"
REMOTE_DIR="${OTKLIK_DEPLOY_PATH:-/opt/otklick}"
COMPOSE="docker compose -f docker-compose.prod.yml"

SSH=(ssh -i "$KEY" -o StrictHostKeyChecking=accept-new "$USER@$HOST")

echo "==> [1/4] Перенос кода → $USER@$HOST:$REMOTE_DIR"
# COPYFILE_DISABLE=1 — не класть в архив macOS-метаданные (._-файлы).
COPYFILE_DISABLE=1 tar czf - \
  --exclude=vendor \
  --exclude=node_modules \
  --exclude=.env \
  --exclude=public/build \
  --exclude='storage/app/public/*' \
  --exclude=backups \
  --exclude=.git \
  -C "$PROJECT_DIR" . | "${SSH[@]}" "tar xzf - -C '$REMOTE_DIR'"

echo "==> [2/4] Бэкап БД (pg_dump) ДО миграций"
# Дамп в backups/db_<ts>.sql.gz, держим последние 10. Падение бэкапа (set -e)
# останавливает деплой — не катим миграции без свежего дампа.
"${SSH[@]}" "cd '$REMOTE_DIR' && mkdir -p backups && ts=\$(date +%Y%m%d_%H%M%S) && \
  $COMPOSE exec -T pgsql pg_dump -U otklick -d otklick | gzip > \"backups/db_\$ts.sql.gz\" && \
  test -s \"backups/db_\$ts.sql.gz\" && \
  ls -1t backups/db_*.sql.gz | tail -n +11 | xargs -r rm -f && \
  echo \"  бэкап: backups/db_\$ts.sql.gz (\$(du -h \"backups/db_\$ts.sql.gz\" | cut -f1))\""

echo "==> [3/4] Пересборка и перезапуск стека (сборка фронтенда + миграции на старте app)"
"${SSH[@]}" "cd '$REMOTE_DIR' && $COMPOSE up -d --build"

echo "==> [4/4] Проверки"
"${SSH[@]}" "cd '$REMOTE_DIR' && $COMPOSE exec -T app php artisan migrate:status | tail -n 8"
echo "--- Контейнеры ---"
"${SSH[@]}" "cd '$REMOTE_DIR' && $COMPOSE ps"

echo "--- HTTP ---"
for url in "https://otcl1ck.ru" "https://business.otcl1ck.ru/login"; do
  code="$(curl -s -o /dev/null -w '%{http_code}' "$url" || echo '000')"
  echo "  $code  $url"
done

echo "==> Готово."
