# Twitch Text-to-Speech

Petite application légère pour fournir un overlay Text-to-Speech de chat Twitch.

## Stack

- PHP 8.3 avec [Slim](https://www.slimframework.com/) pour le backend HTTP.
- Twig pour les vues serveur.
- SQLite pour stocker les utilisateurs Twitch et leurs préférences TTS.
- JavaScript vanilla dans l’overlay OBS.
- `tmi.js` côté navigateur pour lire le chat Twitch sans serveur WebSocket dédié.
- Web Speech API côté overlay pour limiter la charge serveur, pratique sur Raspberry Pi.

L’application est servie depuis `docroot/`.

## Lancement avec DDEV

```bash
ddev start
ddev composer install
cp .env.example .env
ddev exec php bin/migrate.php
```

Si `ddev start` doit ajouter `twitch-tts.ddev.site` au fichier hosts, lance-le dans un terminal interactif afin de pouvoir saisir ton mot de passe sudo.

Ensuite, configure l’application Twitch avec l’URL de callback :

```text
https://twitch-tts.ddev.site/auth/twitch/callback
```

Puis renseigne dans `.env` :

```dotenv
TWITCH_CLIENT_ID=...
TWITCH_CLIENT_SECRET=...
TWITCH_REDIRECT_URI=https://twitch-tts.ddev.site/auth/twitch/callback
APP_URL=https://twitch-tts.ddev.site
APP_SECRET=une-valeur-longue-et-aleatoire
```

## Utilisation

1. Ouvre `https://twitch-tts.ddev.site`.
2. Connecte-toi avec Twitch.
3. Configure les préférences TTS dans `/dashboard`.
4. Copie l’URL overlay générée.
5. Ajoute cette URL dans OBS comme source navigateur.

## Raspberry Pi

Pour un Raspberry Pi, cette architecture reste volontairement simple :

- pas de build frontend,
- pas de Node.js obligatoire en production,
- pas de serveur temps réel,
- stockage local SQLite,
- synthèse vocale exécutée par le navigateur/OBS qui affiche l’overlay.

Une installation PHP-FPM + Caddy/Nginx/Apache suffit en production. DDEV reste prévu pour le développement local.

## Docker sans DDEV

Une image Docker de production est fournie pour lancer l’application facilement sur un Raspberry Pi ou un serveur Linux.

Elle utilise :

- PHP 8.3 avec Apache,
- SQLite via `pdo_sqlite`,
- Apache configuré avec `docroot/` comme `DocumentRoot`,
- un dossier hôte persistant `twitchtts-data/` pour la configuration et la base SQLite,
- une migration automatique de la base au démarrage du conteneur.

### Préparer la configuration

Crée le dossier qui contiendra les fichiers persistants sur la machine Docker :

```bash
mkdir -p twitchtts-data/var
cp .env.example twitchtts-data/.env
```

Puis adapte `twitchtts-data/.env` pour ton environnement de production :

```dotenv
APP_ENV=prod
APP_URL=https://tts.example.com
APP_SECRET=une-valeur-longue-et-aleatoire

TWITCH_CLIENT_ID=...
TWITCH_CLIENT_SECRET=...
TWITCH_REDIRECT_URI=https://tts.example.com/auth/twitch/callback

DATABASE_PATH=var/app.sqlite
```

L’URL `TWITCH_REDIRECT_URI` doit être déclarée à l’identique dans la console Twitch Developer.

Le fichier `.env` et la base SQLite restent donc sur la machine qui exécute Docker :

```text
twitchtts-data/
├── .env
└── var/
    └── app.sqlite
```

Le dossier `twitchtts-data/` est ignoré par Git.

### Lancer avec Docker Compose

```bash
docker compose -f docker-compose.prod.yml up -d --build
```

L’application sera disponible localement sur :

```text
http://localhost:8080
```

ou depuis une autre machine du réseau :

```text
http://IP_DU_RASPBERRY_PI:8080
```

### Logs

```bash
docker compose -f docker-compose.prod.yml logs -f
```

### Arrêt

```bash
docker compose -f docker-compose.prod.yml down
```

Les données SQLite restent conservées dans `twitchtts-data/var/app.sqlite`.

### Sauvegarde

Pour sauvegarder la configuration et la base SQLite :

```bash
tar czf twitch-tts-backup.tar.gz twitchtts-data/
```

### HTTPS recommandé

Pour Twitch OAuth, il est recommandé d’exposer l’application derrière une URL HTTPS.

Deux options simples :

- Caddy ou Nginx en reverse proxy devant `http://localhost:8080`,
- Cloudflare Tunnel si tu ne veux pas ouvrir de port sur ta box.

Si `APP_URL` commence par `https://`, les cookies de session sont marqués `Secure`. Il faut donc accéder réellement à l’application en HTTPS.
