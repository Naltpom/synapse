# API Symfony servie par FrankenPHP (Caddy + PHP embarqué).
FROM dunglas/frankenphp:1-php8.4 AS app

RUN install-php-extensions \
    pdo_pgsql \
    intl \
    zip \
    opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

ENV APP_ENV=prod

# Dépendances d'abord : le cache Docker évite de réinstaller à chaque changement de code.
COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --no-progress

COPY . .
RUN composer dump-autoload --optimize --no-dev \
    && mkdir -p var

COPY docker/entrypoint.sh /usr/local/bin/synapse-entrypoint
RUN chmod +x /usr/local/bin/synapse-entrypoint

EXPOSE 8080

ENTRYPOINT ["synapse-entrypoint"]
CMD ["frankenphp", "php-server", "--listen", ":8080", "--root", "public/"]
