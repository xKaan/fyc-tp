FROM php:8.4-fpm-alpine

# Réglages de l'étude : les valeurs par défaut d'une installation courante.
COPY etude.ini /usr/local/etc/php/conf.d/etude.ini
COPY www.conf /usr/local/etc/php-fpm.d/zz-etude.conf

WORKDIR /var/www/html
