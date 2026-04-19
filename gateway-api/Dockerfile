FROM php:8.2-cli

WORKDIR /var/www/html

RUN apt-get update \
    && apt-get install -y --no-install-recommends libsqlite3-dev pkg-config \
    && docker-php-ext-install pdo pdo_sqlite sockets \
    && rm -rf /var/lib/apt/lists/*

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
