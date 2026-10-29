#!/bin/sh
set -e

cd /var/www/html

mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs

# Generate APP_KEY / JWT_SECRET on first start if not provided, and keep them in
# the storage volume so tokens stay valid across container restarts.
SECRETS_DIR=storage/app/.secrets
mkdir -p "$SECRETS_DIR"

if [ -z "$APP_KEY" ]; then
    [ -f "$SECRETS_DIR/app_key" ] || echo "base64:$(head -c 32 /dev/urandom | base64)" > "$SECRETS_DIR/app_key"
    export APP_KEY="$(cat "$SECRETS_DIR/app_key")"
fi

if [ -z "$JWT_SECRET" ]; then
    [ -f "$SECRETS_DIR/jwt_secret" ] || head -c 48 /dev/urandom | base64 | tr -d '\n' > "$SECRETS_DIR/jwt_secret"
    export JWT_SECRET="$(cat "$SECRETS_DIR/jwt_secret")"
fi
chmod 600 "$SECRETS_DIR"/* 2>/dev/null || true

echo "Waiting for the database at ${DB_HOST}:${DB_PORT}..."
tries=0
until php -r 'try { new PDO("mysql:host=".getenv("DB_HOST").";port=".getenv("DB_PORT"), getenv("DB_USERNAME"), getenv("DB_PASSWORD")); exit(0); } catch (Throwable $e) { exit(1); }'; do
    tries=$((tries + 1))
    if [ "$tries" -ge 60 ]; then
        echo "Database is not reachable, giving up." >&2
        exit 1
    fi
    sleep 2
done

php artisan migrate --force

if [ "${SEED_DEMO_DATA:-true}" = "true" ]; then
    php artisan db:seed --force
fi

php artisan storage:link 2>/dev/null || true
php artisan config:cache

chown -R www-data:www-data storage bootstrap/cache

exec "$@"
