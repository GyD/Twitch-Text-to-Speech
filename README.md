# Twitch Text-to-Speech

Petite application légère pour fournir un overlay Text-to-Speech de chat Twitch.

## Stack

- PHP 8.3 avec [Slim](https://www.slimframework.com/) pour le backend HTTP.
- Twig pour les vues serveur.
- SQLite pour stocker les utilisateurs Twitch et leurs préférences TTS.
- JavaScript vanilla dans l’overlay OBS.
- `tmi.js` côté navigateur pour lire le chat Twitch sans serveur WebSocket dédié.
- Web Speech API côté overlay pour limiter la charge serveur, pratique sur Raspberry Pi.

L’ancienne démo statique est conservée dans `public/`. La nouvelle application est servie depuis `docroot/`.

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
