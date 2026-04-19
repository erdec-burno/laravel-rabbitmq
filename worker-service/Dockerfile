FROM php:8.2-cli

WORKDIR /var/www/html

RUN docker-php-ext-install pdo pdo_sqlite

CMD ["php", "artisan", "queue:work", "--tries=3", "--verbose"]
