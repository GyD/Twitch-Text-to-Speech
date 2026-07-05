#!/bin/sh
set -eu

mkdir -p /var/www/html/var
mkdir -p /etc/twitch-tts/certs
chown -R www-data:www-data /var/www/html/var

if [ ! -f /etc/twitch-tts/certs/server.crt ] || [ ! -f /etc/twitch-tts/certs/server.key ]; then
    openssl req -x509 -nodes -newkey rsa:2048 \
        -days 825 \
        -keyout /etc/twitch-tts/certs/server.key \
        -out /etc/twitch-tts/certs/server.crt \
        -subj "/CN=${TLS_CERT_COMMON_NAME:-localhost}" \
        -addext "subjectAltName=DNS:${TLS_CERT_COMMON_NAME:-localhost},DNS:localhost,IP:127.0.0.1"
    chmod 600 /etc/twitch-tts/certs/server.key
fi

php /var/www/html/bin/migrate.php
chown -R www-data:www-data /var/www/html/var

exec "$@"