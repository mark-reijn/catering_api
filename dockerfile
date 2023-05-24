FROM composer:2 as composer_stage

RUN rm -rf /var/www && mkdir -p /var/www/html
WORKDIR /var/www/html

COPY composer.json composer.lock ./

# This are production settings, I'm running with 'no-dev', adjust accordingly
# if you need it
RUN composer install --ignore-platform-reqs --prefer-dist --no-scripts --no-progress --no-interaction --no-dev --no-autoloader

RUN composer dump-autoload --optimize --apcu --no-dev

FROM php:8.2.4-apache

COPY . /usr/src/myapp
WORKDIR /usr/src/myapp


COPY --from=composer_stage /var/www/html /var/www/html