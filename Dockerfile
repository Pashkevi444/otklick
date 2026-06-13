# syntax=docker/dockerfile:1

# RoadRunner-бинарь берём из официального образа
FROM ghcr.io/roadrunner-server/roadrunner:2025.1 AS roadrunner

# Сборка фронтенд-ассетов (Inertia + Vue + Tailwind)
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY vite.config.js tsconfig.json ./
COPY resources ./resources
RUN npm run build

FROM php:8.4-cli AS app

# Системные зависимости и PHP-расширения
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libpq-dev libzip-dev libicu-dev \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql pgsql bcmath intl zip pcntl sockets opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# RoadRunner
COPY --from=roadrunner /usr/bin/rr /usr/local/bin/rr

WORKDIR /var/www/html

# Сначала зависимости (кэш слоёв)
COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-scripts --prefer-dist --no-dev --no-autoloader

# Затем код
COPY . .
RUN composer dump-autoload --optimize

# Собранные ассеты из node-стадии
COPY --from=assets /app/public/build ./public/build

COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

EXPOSE 8000

ENTRYPOINT ["entrypoint"]
CMD ["php", "artisan", "octane:start", "--server=roadrunner", "--host=0.0.0.0", "--port=8000"]
