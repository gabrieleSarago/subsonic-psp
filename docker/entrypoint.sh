#!/bin/sh
# ---------------------------------------------------------------------------
# Container entry point
# ---------------------------------------------------------------------------
# Everything here is configuration taken from the environment, so one image
# serves every deployment and no settings are baked into a layer.
#
# The image already runs as the unprivileged user the server needs, so this
# script only has to produce the configuration that cannot be known in advance
# and hand over to the server. It is prepared to also be run as root, so that a
# bind mounted directory can be fixed up before starting.

set -eu

cd /app

# The variables below are required. Failing here is better than starting an
# application that answers every request with an error about them.
: "${APP_URL:?APP_URL mancante: l'indirizzo con cui il dispositivo raggiunge questa applicazione}"
: "${SUBSONIC_HOST:?SUBSONIC_HOST mancante: l'indirizzo del server Navidrome}"
: "${SUBSONIC_USERNAME:?SUBSONIC_USERNAME mancante}"
: "${SUBSONIC_PASSWORD:?SUBSONIC_PASSWORD mancante}"

# The port the server listens on is taken from the environment too, so that the
# published port and the port listened on cannot drift apart.
: "${APP_PORT:=8000}"
export APP_PORT

if [ ! -f .env ]; then
    printf 'APP_NAME="%s"\nAPP_ENV=production\nAPP_KEY=\nAPP_DEBUG=false\nAPP_URL=%s\n' \
        "${APP_NAME:-PSP RSS}" "$APP_URL" > .env

    printf 'LOG_CHANNEL=stderr\nLOG_LEVEL=info\n' >> .env

    printf 'CACHE_STORE=file\nSESSION_DRIVER=file\nQUEUE_CONNECTION=sync\n' >> .env

    printf 'SUBSONIC_HOST=%s\nSUBSONIC_USERNAME=%s\nSUBSONIC_PASSWORD=%s\n' \
        "$SUBSONIC_HOST" "$SUBSONIC_USERNAME" "$SUBSONIC_PASSWORD" >> .env

    if [ -n "${SUBSONIC_PSP_BITRATE:-}" ]; then
        printf 'SUBSONIC_PSP_BITRATE=%s\n' "$SUBSONIC_PSP_BITRATE" >> .env
    fi

    if [ -n "${PSP_FEED_PAGE_SIZE:-}" ]; then
        printf 'PSP_FEED_PAGE_SIZE=%s\n' "$PSP_FEED_PAGE_SIZE" >> .env
    fi

    if [ -n "${RADIOS_FILE:-}" ]; then
        printf 'RADIOS_FILE=%s\n' "$RADIOS_FILE" >> .env
    fi

    if [ -n "${RADIOS_FFMPEG_BIN:-}" ]; then
        printf 'RADIOS_FFMPEG_BIN=%s\n' "$RADIOS_FFMPEG_BIN" >> .env
    fi

    if [ -n "${RADIOS_PSP_BITRATE:-}" ]; then
        printf 'RADIOS_PSP_BITRATE=%s\n' "$RADIOS_PSP_BITRATE" >> .env
    fi

    if [ -n "${RADIOS_PROBE_TIMEOUT:-}" ]; then
        printf 'RADIOS_PROBE_TIMEOUT=%s\n' "$RADIOS_PROBE_TIMEOUT" >> .env
    fi

    if [ -n "${RADIOS_FIRST_CHUNK_TIMEOUT:-}" ]; then
        printf 'RADIOS_FIRST_CHUNK_TIMEOUT=%s\n' "$RADIOS_FIRST_CHUNK_TIMEOUT" >> .env
    fi

    chmod 600 .env

    # The key is written to the file rather than printed, so that it never ends
    # up in the logs of whoever starts the container.
    php artisan key:generate --force --no-interaction
    php artisan config:cache --no-interaction
    php artisan route:cache --no-interaction
    php artisan view:cache --no-interaction
fi

if [ "$(id -u)" = "0" ]; then
    # Started as root: the writable directories may belong to whoever wrote
    # them, so they are handed to the user the server runs as. A cache that
    # cannot be written would fail on the first request with a message that
    # says nothing about its cause.
    chown -R www-data:www-data storage bootstrap/cache
    chmod -R ug+rwX storage bootstrap/cache

    exec su -s /bin/sh www-data -c 'exec "$@"' -- "$@"
fi

exec "$@"