FROM php:7.4-cli
WORKDIR /usr/src/app
COPY composer.json /usr/src/app/
COPY --from=composer /usr/bin/composer /usr/bin/composer

RUN cp /usr/local/etc/php/php.ini-development /usr/local/etc/php/php.ini && \
    apt-get update && \
    apt-get install git libonig-dev libzip-dev unzip wait-for-it -y && \
    docker-php-ext-install mbstring sockets zip && \
    composer install && \
    apt-get clean
COPY mqbridge.php /usr/src/app/
CMD [ "php", "./mqbridge.php" ]
