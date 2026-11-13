#!/bin/sh
set -e

echo "[GardaCyber] boot: generating APP_KEY if missing"
if [ -z "$APP_KEY" ]; then
  export APP_KEY="$(php artisan key:generate --show)"
fi

echo "[GardaCyber] boot: forcing queue connection to database"
# Scan jobs must be processed by the on-server worker, never synchronously
# inside an HTTP request (and never booted locally by BackgroundScanStarter).
if [ -z "$QUEUE_CONNECTION" ] || [ "$QUEUE_CONNECTION" = "sync" ]; then
  export QUEUE_CONNECTION=database
fi

echo "[GardaCyber] boot: preparing sqlite database"
mkdir -p database
if [ ! -f database/database.sqlite ]; then
  touch database/database.sqlite
fi

echo "[GardaCyber] boot: storage link"
php artisan storage:link >/dev/null 2>&1 || true

echo "[GardaCyber] boot: running migrations"
php artisan migrate --force

echo "[GardaCyber] boot: seeding CVE baseline"
php artisan cve:baseline

echo "[GardaCyber] boot: seeding demo data"
GARDA_SEED_FORCE=true php artisan app:demo-seed

echo "[GardaCyber] boot: caching config, routes, views"
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "[GardaCyber] boot: starting background queue worker"
mkdir -p storage/logs
php artisan queue:work database \
  --queue=default \
  --tries=3 \
  --timeout=300 \
  --sleep=2 \
  > storage/logs/queue-worker.log 2>&1 &

echo "[GardaCyber] boot: starting schedule runner"
(while true; do
  php artisan schedule:run
  sleep 60
done) > storage/logs/scheduler.log 2>&1 &

echo "[GardaCyber] boot: starting server on :$PORT"
exec php artisan serve --host=0.0.0.0 --port="${PORT:-8080}"
