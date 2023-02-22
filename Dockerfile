FROM php:7.4-cli

RUN apt-get update
RUN apt-get install -y libzip-dev zip && docker-php-ext-install zip

COPY . /usr/opt/app

WORKDIR /usr/opt/app
EXPOSE 80

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

RUN composer install

CMD ["php", "-S", "0.0.0.0:80", "index.php"]
