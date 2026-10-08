# HMS Sync — Laravel Octane (RoadRunner) image.
#
#   docker build -f docker/octane/Dockerfile -t hms-sync .
#   docker run --env-file .env -p 8000:8000 hms-sync
#
# The same image runs the queue worker, scheduler and Reverb by overriding
# the command (see docker/compose.yaml).

FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --optimize --no-dev

FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY resources ./resources
COPY vite.config.js ./
COPY public ./public
RUN npm run build

FROM php:8.3-cli-bookworm

# Extensions: PostgreSQL, Redis (phpredis), Octane (pcntl, sockets), PDF (gd), intl, zip.
ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/download/2.7.24/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_pgsql pgsql redis pcntl sockets gd intl zip bcmath opcache \
 && apt-get update && apt-get install -y --no-install-recommends postgresql-client \
 && rm -rf /var/lib/apt/lists/*

COPY docker/octane/php.ini /usr/local/etc/php/conf.d/zz-hms.ini

WORKDIR /var/www/html
COPY --from=vendor /app /var/www/html
COPY --from=assets /app/public/build /var/www/html/public/build

# RoadRunner binary matching the installed spiral/roadrunner-cli.
RUN php vendor/bin/rr get-binary --location /usr/local/bin --no-interaction \
 && ln -s ../storage/app/public public/storage \
 && chown -R www-data:www-data storage bootstrap/cache

USER www-data
EXPOSE 8000

CMD ["php", "artisan", "octane:start", "--server=roadrunner", "--host=0.0.0.0", "--port=8000", "--workers=auto", "--max-requests=500"]
