#!/usr/bin/env bash
set -e

echo "Installing Composer dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction

echo "Installing NPM dependencies..."
npm ci

echo "Building assets..."
npm run build

echo "Optimizing Laravel..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "Running migrations..."
php artisan migrate --force --no-interaction

echo "Build complete!"
