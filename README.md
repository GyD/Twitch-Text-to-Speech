# Twitch Text-to-Speech

A lightweight Twitch chat Text-to-Speech overlay application.

## Stack

- PHP 8.3 with [Slim](https://www.slimframework.com/) for the HTTP backend.
- Twig for server-rendered views.
- SQLite to store Twitch users and their TTS preferences.
- Vanilla JavaScript in the OBS overlay.
- Browser-side `tmi.js` to read Twitch chat without a dedicated WebSocket server.
- Browser-side Web Speech API to keep server resource usage low, which is useful on a Raspberry Pi.

The application is served from `docroot/`.

## Development with DDEV

```bash
ddev start
ddev composer install
cp config/settings.local.example.php config/settings.local.php
ddev exec php bin/migrate.php
```

If `ddev start` needs to add `twitch-tts.ddev.site` to your hosts file, run it from an interactive terminal so you can enter your sudo password.

Then configure your Twitch application with this callback URL:

```text
https://twitch-tts.ddev.site/auth/twitch/callback
```

Update `config/settings.local.php` with your Twitch application credentials:

```php
<?php

declare(strict_types=1);

return [
    'app' => [
        'env' => 'dev',
        'url' => 'https://twitch-tts.ddev.site',
    ],
    'database' => [
        'path' => 'var/app.sqlite',
    ],
    'twitch' => [
        'client_id' => '...',
        'client_secret' => '...',
        'redirect_uri' => 'https://twitch-tts.ddev.site/auth/twitch/callback',
    ],
];
```

## Usage

1. Open `https://twitch-tts.ddev.site`.
2. Sign in with Twitch.
3. Configure your TTS preferences from `/dashboard`.
4. Copy the generated overlay URL.
5. Add that URL to OBS as a browser source.

## Raspberry Pi

The architecture is intentionally simple for Raspberry Pi usage:

- no frontend build step,
- no Node.js requirement in production,
- no real-time backend server,
- local SQLite storage,
- speech synthesis runs in the browser or OBS instance that displays the overlay.

A PHP-FPM + Caddy/Nginx/Apache setup is enough for production. DDEV is intended for local development only.

## Docker without DDEV

A production Docker image is provided to run the application easily on a Raspberry Pi or Linux server.

It uses:

- PHP 8.3 with Apache,
- SQLite through `pdo_sqlite`,
- Apache configured with `docroot/` as the `DocumentRoot`,
- a persistent host directory named `twitchtts-data/` for configuration, certificates, and the SQLite database,
- HTTPS served directly by Apache on the internal `443` port,
- automatic database migrations when the container starts.

### Prepare the configuration

Create the directory that will contain persistent files on the Docker host:

```bash
mkdir -p twitchtts-data/var twitchtts-data/certs
cp docker.env.example twitchtts-data/docker.env
cp config/settings.local.example.php twitchtts-data/settings.local.php
```

Then edit `twitchtts-data/docker.env` for Docker-only settings:

```dotenv
TWITCHTTS_HTTP_PORT=7317
TWITCHTTS_HTTPS_PORT=8945
TLS_CERT_COMMON_NAME=ras1
```

Then edit `twitchtts-data/settings.local.php` for PHP application settings:

```php
<?php

declare(strict_types=1);

return [
    'app' => [
        'env' => 'prod',
        'url' => 'https://ras1:8945',
    ],
    'database' => [
        'path' => 'var/app.sqlite',
    ],
    'twitch' => [
        'client_id' => '...',
        'client_secret' => '...',
        'redirect_uri' => 'https://ras1:8945/auth/twitch/callback',
    ],
];
```

The `twitch.redirect_uri` value must be registered exactly as-is in the Twitch Developer Console.

The exposed HTTP port on the Docker host can be configured with `TWITCHTTS_HTTP_PORT`. For example, to expose the application on port `8090`:

```dotenv
TWITCHTTS_HTTP_PORT=8090
```

The internal container port remains `80`; only the Raspberry Pi entry port changes.

The exposed HTTPS port is configured with `TWITCHTTS_HTTPS_PORT`. By default, HTTPS is exposed on port `8945`:

```dotenv
TWITCHTTS_HTTPS_PORT=8945
```

The container automatically generates a self-signed certificate in `twitchtts-data/certs/` if no certificate exists yet. `TLS_CERT_COMMON_NAME` should match the hostname used in the browser, for example `ras1` for `https://ras1:8945/`.

The PHP settings, Docker environment file, certificates, and SQLite database stay on the Docker host:

```text
twitchtts-data/
├── docker.env
├── settings.local.php
├── certs/
│   ├── server.crt
│   └── server.key
└── var/
    └── app.sqlite
```

The `twitchtts-data/` directory is ignored by Git.

### Start with Docker Compose

```bash
docker compose --env-file ./twitchtts-data/docker.env -f docker-compose.prod.yml up -d --build
```

The application will be available locally at:

```text
http://localhost:7317
https://localhost:8945
```

If you changed `TWITCHTTS_HTTP_PORT`, replace `7317` with your configured value.

From another machine on the network, use:

```text
http://RASPBERRY_PI_IP:7317
https://RASPBERRY_PI_IP:8945
```

Or, if your network resolves `ras1`:

```text
https://ras1:8945
```

With the self-signed certificate, the browser will show a security warning the first time. You need to accept it or install `twitchtts-data/certs/server.crt` as a trusted certificate on client machines.

### Update the application on Raspberry Pi

From the application directory on the Raspberry Pi, first create a backup of the persistent data:

```bash
tar czf twitch-tts-backup-$(date +%Y%m%d-%H%M%S).tar.gz twitchtts-data/
```

Then pull the latest code:

```bash
git pull
```

If you deploy from a specific branch, for example `main`, use:

```bash
git pull origin main
```

Rebuild the image and restart the container:

```bash
docker compose --env-file ./twitchtts-data/docker.env -f docker-compose.prod.yml up -d --build
```

This command rebuilds the `twitch-tts:latest` image, recreates the container when needed, keeps the persistent `twitchtts-data/` directory, and runs database migrations automatically through `docker/entrypoint.sh`.

Check the logs after the update:

```bash
docker compose --env-file ./twitchtts-data/docker.env -f docker-compose.prod.yml logs -f
```

You can stop following logs with `Ctrl+C`; the container will keep running.

Optional cleanup to free disk space on the Raspberry Pi:

```bash
docker image prune -f
```

Short update sequence:

```bash
cd /path/to/Twitch-Text-to-Speech
tar czf twitch-tts-backup-$(date +%Y%m%d-%H%M%S).tar.gz twitchtts-data/
git pull
docker compose --env-file ./twitchtts-data/docker.env -f docker-compose.prod.yml up -d --build
docker compose --env-file ./twitchtts-data/docker.env -f docker-compose.prod.yml logs -f
```

### Logs

```bash
docker compose --env-file ./twitchtts-data/docker.env -f docker-compose.prod.yml logs -f
```

### Stop

```bash
docker compose --env-file ./twitchtts-data/docker.env -f docker-compose.prod.yml down
```

The SQLite data remains stored in `twitchtts-data/var/app.sqlite`.

### Backup

To back up the configuration, certificates, and SQLite database:

```bash
tar czf twitch-tts-backup.tar.gz twitchtts-data/
```

### HTTPS

For Twitch OAuth, using HTTPS in `app.url` and `twitch.redirect_uri` is recommended.

Direct local HTTPS option with the container HTTPS endpoint:

```php
return [
    'app' => [
        'url' => 'https://ras1:8945',
    ],
    'twitch' => [
        'redirect_uri' => 'https://ras1:8945/auth/twitch/callback',
    ],
];
```

Keep `TLS_CERT_COMMON_NAME=ras1` in `twitchtts-data/docker.env` so the self-signed certificate matches the local hostname.

Alternatives if you want a publicly trusted certificate:

- Caddy or Nginx as a reverse proxy in front of `http://localhost:7317`,
- Cloudflare Tunnel if you do not want to open a port on your router.

If `app.url` starts with `https://`, session cookies are marked as `Secure`. You must therefore access the application over HTTPS.
