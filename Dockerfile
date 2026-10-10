# syntax=docker/dockerfile:1

# ---------------------------------------------------------------------------
# Dependencies
# ---------------------------------------------------------------------------
# The autoloader only is needed to run Composer itself, so it is built in a
# stage that never ships.
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader

# ---------------------------------------------------------------------------
# Front end
# ---------------------------------------------------------------------------
# There is none. The page the PSP opens is plain HTML with an inline style
# sheet, and the feeds are XML: neither needs a build step, so no node is
# involved here either.

# ---------------------------------------------------------------------------
# Application
# ---------------------------------------------------------------------------
FROM dunglas/frankenphp:php8.5-bookworm AS app

# The base image already carries curl, mbstring, the XML extensions and the
# SQLite driver. Only the ones this application does not have are added.
RUN install-php-extensions \
    intl \
    opcache \
    pcntl \
    zip \
    && rm -rf /tmp/*

# The radio section transcodes every station to MP3 in real time with ffmpeg,
# so the binary must live inside the image.
RUN apt-get update \
    && apt-get install -y --no-install-recommends ffmpeg \
    && rm -rf /var/lib/apt/lists/*

# Never read a configuration file from the host: every setting comes from the
# environment, which is where docker-compose passes it.
#
# The directories Caddy keeps its state in point into a writable place, because
# the ones in the image belong to root and the server runs unprivileged.
ENV APP_CONFIG_CACHE=/tmp/config.php \
    APP_ENV=production \
    APP_DEBUG=false \
    CADDY_HOME=/tmp/caddy \
    XDG_CONFIG_HOME=/tmp/caddy/config \
    XDG_DATA_HOME=/tmp/caddy/data \
    COMPOSER_ALLOW_SUPERUSER=1

COPY --from=vendor /app/vendor ./vendor

WORKDIR /app

COPY . .

# The image carries no key and no environment file: both are produced on first
# boot, so the same image works for every deployment and neither secret ever
# ends up inside a layer.
RUN rm -f .env \
    && mkdir -p \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/testing \
        storage/framework/views \
        storage/logs \
    && rm -rf node_modules storage/framework/cache/data/* \
    && chmod +x docker/entrypoint.sh \
    && install -m 644 docker/php.ini /usr/local/etc/php/conf.d/zz-app.ini \
    && chown www-data:www-data /app \
    && chown -R www-data:www-data storage bootstrap/cache

# A mistake in the server configuration is caught here rather than when the
# container starts and refuses to come up.
RUN frankenphp fmt --overwrite docker/Caddyfile \
    && APP_PORT=8000 frankenphp adapt --config docker/Caddyfile > /dev/null

# No EXPOSE: the port the server listens on is decided by APP_PORT, so a fixed
# number here would only ever be a number to be contradicted. docker-compose
# publishes the same variable on the host side.

USER www-data

# The endpoint the application already exposes for this purpose. PHP is the one
# program guaranteed to be present, so nothing has to be installed to check it.
HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD ["php", "-r", "exit(@file_get_contents('http://127.0.0.1:'.(getenv('APP_PORT') ?: 8000).'/up') === false ? 1 : 0);"]

ENTRYPOINT ["/app/docker/entrypoint.sh"]
CMD ["frankenphp", "run", "--config", "/app/docker/Caddyfile"]