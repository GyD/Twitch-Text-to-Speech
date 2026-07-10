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
    'twig' => [
        'cache' => true,
    ],
    'twitch' => [
        'client_id' => '',
        'client_secret' => '',
        'redirect_uri' => 'https://twitch-tts.ddev.site/auth/twitch/callback',
    ],
];