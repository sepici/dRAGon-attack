#!/bin/bash
set -e

echo "→ Installing PHP dependencies (production)..."
composer install --no-dev --optimize-autoloader --no-interaction

echo "→ Installing frontend dependencies..."
npm ci --omit=dev

echo "→ Building frontend assets..."
npm run build

echo "→ Running migrations..."
php artisan migrate --force

echo "→ Caching config / routes / views..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "→ Ensuring storage symlink..."
php artisan storage:link || true

echo "→ Done."
