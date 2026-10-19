FROM php:8.2-cli-alpine

RUN apk add --no-cache git unzip oniguruma-dev \
    && docker-php-ext-install pdo_mysql bcmath mbstring

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www
COPY . .

EXPOSE 8000

CMD ["sh", "-c", "composer install --no-interaction --prefer-dist && php artisan serve --host=0.0.0.0 --port=8000"]
