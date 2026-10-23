#!/bin/sh
# Runs every time the container starts (each deploy and restart on Render).
set -e
cd /var/www/html

# The address: Render tells us (RENDER_EXTERNAL_URL) unless APP_URL is set by hand (a custom domain).
export APP_URL="${APP_URL:-${RENDER_EXTERNAL_URL:-http://localhost:${PORT}}}"

# Laravel needs a 32-byte key written as base64:…; Render's generated secret is turned into one,
# always the same one for the same secret (so logins and saved data survive restarts).
if [ -z "$APP_KEY" ]; then
    echo "APP_KEY is not set. In Render: Environment → add APP_KEY (any long random text)." >&2
    exit 1
fi
case "$APP_KEY" in
    base64:*) ;;
    *) export APP_KEY="$(php -r 'echo "base64:".base64_encode(hash("sha256", getenv("APP_KEY"), true));')" ;;
esac

# Apache's port (docker/apache.conf reads ${PORT}); the image's own ports.conf would also take 80.
sed -i 's/^Listen 80$//' /etc/apache2/ports.conf

# Files the app keeps (recordings, pictures, settings files) live in storage/app: on Render that is
# a persistent disk, which starts empty, so make the folders it needs.
mkdir -p storage/app/public storage/app/private storage/framework/cache/data storage/framework/sessions \
         storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
[ -L public/storage ] || ln -sfn ../storage/app/public public/storage

php artisan config:clear >/dev/null
php artisan migrate --force
# The course, lessons, culture, proverbs and blog drafts. Every seeder is add-only: running it
# again never changes or removes anything a reviewer or admin did. RUN_SEEDERS=false skips it.
if [ "${RUN_SEEDERS:-true}" = "true" ]; then
    php artisan db:seed --force
fi
# Recordings shipped in database/audio-pack (php artisan app:export-audio on the computer that made them).
# Without a lasting disk their files are gone after every restart, so they are put back each time.
php artisan app:import-audio || echo "Recordings could not be restored; the site runs without them."
# Make an existing account an admin (register in the app first, then restart the service).
if [ -n "$ADMIN_EMAIL" ]; then
    php artisan app:make-admin "$ADMIN_EMAIL" || true
fi

php artisan config:cache
php artisan route:cache
php artisan view:cache
# The caches above were written as root; the website runs as www-data and must be able to update them.
chown -R www-data:www-data storage bootstrap/cache

# The scheduler (daily reminders, tidying old guest accounts) runs beside the web server.
su -s /bin/sh www-data -c "php artisan schedule:work" &

exec apache2-foreground
