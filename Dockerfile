FROM php:8.3-fpm
WORKDIR /var/www/app

# Installing necessary packages inside docker
RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-install zip pdo pdo_mysql

# This actually installs necessary composer tools
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# This installs nesessary node + npm tools
COPY --from=node:22-bookworm-slim /usr/local /usr/local

COPY . .

RUN composer install
RUN npm ci
RUN npx playwright install --with-deps chromium

ENV PLAYWRIGHT_BROWSERS_PATH=/ms-playwright

# Docker uses this port to run the server 
EXPOSE 9000
