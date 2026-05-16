#!/bin/bash
set -e

# Production deployment script for Cloudways.
# DO NOT run this on your local Mac — `composer install --no-dev` will strip
# your dev tools (Vite, Pint, PHPUnit, etc.) locally and break dev.
#
# Frontend assets are built LOCALLY (npm run build on your Mac) and committed
# to the repo at public/build/, so this script doesn't need npm at all.
#
# Usage on Cloudways:
#   cd ~/applications/<app-name>/public_html
#   bash deploy.sh

echo "→ Installing PHP dependencies (production)..."
composer install --no-dev --optimize-autoloader --no-interaction

echo "→ Running migrations..."
php artisan migrate --force

echo "→ Clearing old caches..."
php artisan optimize:clear

echo "→ Caching config / routes / views..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "→ Ensuring storage symlink..."
php artisan storage:link || true

echo "→ Done."
