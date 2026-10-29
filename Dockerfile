# =========================================================
# Stage 0: PHP CLI base with the extensions composer.json
# requires (ext-intl, ext-zip). Shared by the vendor and
# assets stages so they are only compiled once.
# =========================================================
FROM php:8.4-cli-alpine AS php-base
RUN apk add --no-cache icu-libs libzip \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev libzip-dev \
    && docker-php-ext-install -j"$(nproc)" intl zip \
    && apk del .build-deps

# =========================================================
# Stage 1: Composer dependencies
# =========================================================
FROM php-base AS vendor
WORKDIR /app
RUN apk add --no-cache git unzip
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ARG GITHUB_TOKEN
RUN if [ -n "$GITHUB_TOKEN" ]; then composer config -g github-oauth.github.com "$GITHUB_TOKEN"; fi
COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-dev --optimize-autoloader --no-scripts

# =========================================================
# Stage 2: Frontend asset build
# Needs PHP because the Wayfinder Vite plugin shells out to
# `php artisan wayfinder:generate` during `npm run build`.
# =========================================================
FROM php-base AS assets
WORKDIR /app
RUN apk add --no-cache nodejs npm
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
COPY --from=vendor /app/vendor ./vendor
RUN npm run build

# =========================================================
# Stage 3: Runtime image
# =========================================================
FROM php:8.4-fpm-alpine
WORKDIR /var/www/html
ENV TZ=UTC
RUN apk add --no-cache bash curl nginx supervisor libpng libzip oniguruma icu-libs libpq \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS libpng-dev libzip-dev oniguruma-dev icu-dev postgresql-dev \
    && docker-php-ext-install -j"$(nproc)" pdo pdo_pgsql mbstring bcmath zip intl pcntl opcache \
    && pecl install redis && docker-php-ext-enable redis \
    && apk del .build-deps
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build
COPY . .
RUN mkdir -p storage/framework/{cache,sessions,views,testing} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache
COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh
EXPOSE 80
ENTRYPOINT ["/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
