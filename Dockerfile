FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --no-progress \
    --optimize-autoloader \
    --no-scripts

FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libsqlite3-dev openssl \
    && docker-php-ext-install pdo_sqlite \
    && a2enmod rewrite ssl \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf
COPY docker/apache/server-name.conf /etc/apache2/conf-available/server-name.conf
COPY docker/php/app.ini /usr/local/etc/php/conf.d/app.ini
COPY docker/entrypoint.sh /usr/local/bin/twitch-tts-entrypoint

COPY --chown=www-data:www-data . /var/www/html
COPY --from=vendor --chown=www-data:www-data /app/vendor /var/www/html/vendor

RUN chmod +x /usr/local/bin/twitch-tts-entrypoint \
    && a2enconf server-name \
    && mkdir -p /var/www/html/var /etc/twitch-tts/certs \
    && chown -R www-data:www-data /var/www/html/var

ENV APP_ENV=prod \
    DATABASE_PATH=var/app.sqlite

EXPOSE 80 443

ENTRYPOINT ["twitch-tts-entrypoint"]
CMD ["apache2-foreground"]