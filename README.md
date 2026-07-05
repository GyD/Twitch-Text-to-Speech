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
cp .env.example .env
ddev exec php bin/migrate.php
```

If `ddev start` needs to add `twitch-tts.ddev.site` to your hosts file, run it from an interactive terminal so you can enter your sudo password.

Then configure your Twitch application with this callback URL:

```text
https://twitch-tts.ddev.site/auth/twitch/callback
```

Update `.env` with your Twitch application credentials:

```dotenv
TWITCH_CLIENT_ID=...
TWITCH_CLIENT_SECRET=...
TWITCH_REDIRECT_URI=https://twitch-tts.ddev.site/auth/twitch/callback
APP_URL=https://twitch-tts.ddev.site
APP_SECRET=a-long-random-secret-value
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
cp .env.example twitchtts-data/.env
```

Then edit `twitchtts-data/.env` for your production environment:

```dotenv
APP_ENV=prod
APP_URL=https://tts.example.com
APP_SECRET=a-long-random-secret-value
TWITCHTTS_HTTP_PORT=7317
TWITCHTTS_HTTPS_PORT=8945
TLS_CERT_COMMON_NAME=ras1

TWITCH_CLIENT_ID=...
TWITCH_CLIENT_SECRET=...
TWITCH_REDIRECT_URI=https://ras1:8945/auth/twitch/callback

DATABASE_PATH=var/app.sqlite
```

The `TWITCH_REDIRECT_URI` value must be registered exactly as-is in the Twitch Developer Console.

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

The `.env` file and the SQLite database stay on the Docker host:

```text
twitchtts-data/
├── .env
├── certs/
│   ├── server.crt
│   └── server.key
└── var/
    └── app.sqlite
```

The `twitchtts-data/` directory is ignored by Git.

### Start with Docker Compose

```bash
docker compose --env-file ./twitchtts-data/.env -f docker-compose.prod.yml up -d --build
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
docker compose --env-file ./twitchtts-data/.env -f docker-compose.prod.yml up -d --build
```

This command rebuilds the `twitch-tts:latest` image, recreates the container when needed, keeps the persistent `twitchtts-data/` directory, and runs database migrations automatically through `docker/entrypoint.sh`.

Check the logs after the update:

```bash
docker compose --env-file ./twitchtts-data/.env -f docker-compose.prod.yml logs -f
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
docker compose --env-file ./twitchtts-data/.env -f docker-compose.prod.yml up -d --build
docker compose --env-file ./twitchtts-data/.env -f docker-compose.prod.yml logs -f
```

### Logs

```bash
docker compose --env-file ./twitchtts-data/.env -f docker-compose.prod.yml logs -f
```

### Stop

```bash
docker compose --env-file ./twitchtts-data/.env -f docker-compose.prod.yml down
```

The SQLite data remains stored in `twitchtts-data/var/app.sqlite`.

### Backup

To back up the configuration, certificates, and SQLite database:

```bash
tar czf twitch-tts-backup.tar.gz twitchtts-data/
```

### HTTPS

For Twitch OAuth, using HTTPS in `APP_URL` and `TWITCH_REDIRECT_URI` is recommended.

Direct local HTTPS option with the container HTTPS endpoint:

```dotenv
APP_URL=https://ras1:8945
TWITCH_REDIRECT_URI=https://ras1:8945/auth/twitch/callback
TLS_CERT_COMMON_NAME=ras1
```

Alternatives if you want a publicly trusted certificate:

- Caddy or Nginx as a reverse proxy in front of `http://localhost:7317`,
- Cloudflare Tunnel if you do not want to open a port on your router.

If `APP_URL` starts with `https://`, session cookies are marked as `Secure`. You must therefore access the application over HTTPS.
