FROM dunglas/frankenphp:1-php8.3-bookworm

RUN install-php-extensions \
        intl \
        opcache \
        pcntl \
        pdo_sqlite \
        zip \
    && apt-get update \
    && apt-get install -y --no-install-recommends supervisor unzip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /app

ENV COMPOSER_ALLOW_SUPERUSER=1

COPY composer.json composer.lock ./

RUN composer install \
        --classmap-authoritative \
        --no-ansi \
        --no-dev \
        --no-interaction \
        --no-progress \
        --no-scripts

COPY . .

RUN composer dump-autoload \
        --classmap-authoritative \
        --no-ansi \
        --no-dev \
        --no-interaction \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod +x docker/entrypoint.sh

COPY docker/php.ini /usr/local/etc/php/conf.d/99-production.ini
COPY docker/supervisord.conf /etc/supervisor/conf.d/support-chat.conf

EXPOSE 8080

ENTRYPOINT ["/app/docker/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/support-chat.conf"]
