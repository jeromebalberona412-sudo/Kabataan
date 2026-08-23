#!/bin/sh
set -e

echo "=========================================="
echo "Starting Laravel application entrypoint..."
echo "=========================================="

# ============================================
# Create required system directories
# ============================================
echo "Creating system directories..."
mkdir -p /var/log/nginx || echo "Failed to create /var/log/nginx"
mkdir -p /var/lib/nginx || echo "Failed to create /var/lib/nginx"
mkdir -p /var/cache/nginx || echo "Failed to create /var/cache/nginx"
mkdir -p /run/nginx || echo "Failed to create /run/nginx"
mkdir -p /var/log/supervisor || echo "Failed to create /var/log/supervisor"
mkdir -p /var/run/supervisor || echo "Failed to create /var/run/supervisor"
mkdir -p /var/lib/php/sessions || echo "Failed to create /var/lib/php/sessions"

# ============================================
# Set proper permissions for system directories
# ============================================
echo "Setting system directory permissions..."
chown -R www-data:www-data /var/lib/nginx /var/cache/nginx /run/nginx || echo "Failed to chown nginx directories"
chown -R root:root /var/log/nginx /var/log/supervisor /var/run/supervisor || echo "Failed to chown log directories"
chown -R www-data:www-data /var/lib/php || echo "Failed to chown php directories"
chmod -R 755 /var/log/nginx /var/log/supervisor /var/run/supervisor || echo "Failed to chmod log directories"
chmod -R 755 /var/lib/php || echo "Failed to chmod php directories"

# ============================================
# Create storage directories FIRST (before chown)
# ============================================
echo "Ensuring storage directories exist..."
mkdir -p /var/www/html/storage/framework/{views,cache,sessions} || echo "Failed to create framework directories"
mkdir -p /var/www/html/storage/logs || echo "Failed to create logs directory"
mkdir -p /var/www/html/storage/app/public || echo "Failed to create app/public directory"
mkdir -p /var/www/html/bootstrap/cache || echo "Failed to create bootstrap/cache directory"

# Pre-create laravel.log so PHP-FPM (www-data) always has write access
touch /var/www/html/storage/logs/laravel.log || echo "Failed to touch laravel.log"

# ============================================
# Set proper permissions for Laravel (AFTER mkdir)
# ============================================
echo "Setting Laravel permissions..."
chown -R www-data:www-data /var/www/html/storage || echo "Failed to chown storage"
chown -R www-data:www-data /var/www/html/bootstrap/cache || echo "Failed to chown bootstrap/cache"
chmod -R 775 /var/www/html/storage || echo "Failed to chmod storage"
chmod -R 775 /var/www/html/bootstrap/cache || echo "Failed to chmod bootstrap/cache"

# ============================================
# Verify Laravel files exist
# ============================================
echo "Verifying Laravel installation..."
if [ ! -f "/var/www/html/artisan" ]; then
    echo "ERROR: artisan file not found!"
    ls -la /var/www/html/
    exit 1
fi
echo "artisan file found"

if [ ! -f "/var/www/html/composer.json" ]; then
    echo "ERROR: composer.json not found!"
    exit 1
fi
echo "composer.json found"

# ============================================
# Handle storage link (idempotent)
# ============================================
echo "Setting up storage link..."
if [ -L "/var/www/html/public/storage" ]; then
    echo "Storage symlink already exists, skipping creation"
elif [ -d "/var/www/html/public/storage" ]; then
    echo "Storage exists as a directory, removing and recreating as symlink"
    rm -rf /var/www/html/public/storage || echo "Failed to remove existing storage directory"
    php artisan storage:link || echo "Storage link creation failed"
else
    echo "Creating storage symlink"
    php artisan storage:link || echo "Storage link creation failed"
fi

# ============================================
# Run package:discover (skipped during composer install)
# ============================================
echo "Running package:discover..."
php artisan package:discover --ansi || echo "Package discovery failed or already run"

# ============================================
# Wait for database if DB_HOST is set
# ============================================
if [ -n "$DB_HOST" ]; then
    echo "Waiting for database connection..."
    max_attempts=30
    attempt=0
    while [ $attempt -lt $max_attempts ]; do
        if nc -z "$DB_HOST" 5432; then
            echo "Database is ready!"
            break
        fi
        attempt=$((attempt + 1))
        echo "Waiting for database... (attempt $attempt/$max_attempts)"
        sleep 2
    done
    if [ $attempt -eq $max_attempts ]; then
        echo "Warning: Database connection not established after $max_attempts attempts"
    fi
else
    echo "DB_HOST not set, skipping database wait"
fi

# ============================================
# Run migrations if enabled
# ============================================
if [ "$RUN_MIGRATIONS" = "true" ]; then
    echo "Running database migrations..."
    php artisan migrate --force || echo "Migrations failed or already run"
else
    echo "RUN_MIGRATIONS not set to true, skipping migrations"
fi

# ============================================
# Clear and cache configurations
# ============================================
echo "Optimizing application..."
php artisan config:clear || echo "Config clear failed"
php artisan cache:clear || echo "Cache clear failed"
php artisan route:clear || echo "Route clear failed"
php artisan view:clear || echo "View clear failed"
php artisan event:clear || echo "Event clear failed"

# Only cache if APP_KEY is set (not during image build)
if [ -n "$APP_KEY" ]; then
    echo "Caching configurations..."
    php artisan config:cache || echo "Config cache failed"
    php artisan route:cache || echo "Route cache failed"
    php artisan view:cache || echo "View cache failed"
    php artisan event:cache || echo "Event cache failed"
else
    echo "APP_KEY not set, skipping config caching"
fi

# ============================================
# Optimize Composer autoloader
# ============================================
echo "Optimizing Composer autoloader..."
composer dump-autoload --optimize || echo "Composer optimization failed"

# ============================================
# Configure Nginx to use Render PORT
# ============================================
if [ -n "$PORT" ]; then
    echo "Configuring Nginx to use port $PORT..."
    sed -i "s/listen 8080;/listen $PORT;/g" /etc/nginx/nginx.conf || echo "Failed to configure Nginx port"
else
    echo "PORT not set, using default 8080"
fi

# ============================================
# Verify Supervisor configuration
# ============================================
echo "Verifying Supervisor configuration..."
if [ ! -f "/etc/supervisor/conf.d/supervisord.conf" ]; then
    echo "ERROR: Supervisor configuration file not found!"
    exit 1
fi
echo "Supervisor configuration found"

# ============================================
# Start services
# ============================================
echo "=========================================="
echo "Starting Supervisor..."
echo "=========================================="
exec "$@"
