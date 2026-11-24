# Imagen para publicar PetCare en Render (u otro hosting con Docker)
FROM php:8.4-cli

RUN apt-get update && apt-get install -y --no-install-recommends git unzip libzip-dev libsqlite3-dev libpng-dev libjpeg-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-jpeg --with-freetype \
    && docker-php-ext-install pdo_sqlite zip gd \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . .
RUN composer install --no-dev --optimize-autoloader --no-interaction \
    && chmod +x docker/start.sh

ENV PORT=10000
EXPOSE 10000
CMD ["sh", "docker/start.sh"]
