# Luganda app on Render (or any Docker host): PHP 8.3 + Apache, PostgreSQL, the built CSS/JS.
# Render builds this file; docker/start.sh runs on every start (migrations, add-only seeders,
# caches, the scheduler), then Apache serves public/ on $PORT. See SETUP-LUGANDA.md, "Hosting on Render".

# 1. The CSS and JS (Vite). Fonts are in resources/fonts, so no network is needed for the build itself.
FROM node:22-bookworm-slim AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY vite.config.js ./
COPY resources ./resources
COPY public ./public
RUN npx vite build

# 2. PHP packages, without the development tools.
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --no-scripts --no-autoloader --prefer-dist

# 3. The app.
FROM php:8.3-apache-bookworm
RUN apt-get update \
 && apt-get install -y --no-install-recommends libpq-dev \
 && docker-php-ext-install pdo_pgsql \
 && apt-get purge -y --auto-remove \
 && rm -rf /var/lib/apt/lists/* \
 && a2enmod rewrite headers \
 && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
 && { echo 'upload_max_filesize=12M'; echo 'post_max_size=14M'; echo 'memory_limit=256M'; echo 'expose_php=Off'; } > "$PHP_INI_DIR/conf.d/luganda.ini"

COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY --chown=www-data:www-data . .
COPY --chown=www-data:www-data --from=vendor /app/vendor ./vendor
COPY --chown=www-data:www-data --from=assets /app/public/build ./public/build
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative --no-interaction \
 && rm -f public/hot public/storage \
 && chmod +x docker/start.sh \
 && chown -R www-data:www-data storage bootstrap/cache

ENV PORT=10000 APP_ENV=production APP_DEBUG=false LOG_CHANNEL=stderr
EXPOSE 10000
CMD ["docker/start.sh"]
