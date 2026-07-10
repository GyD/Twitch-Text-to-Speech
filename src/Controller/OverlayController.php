<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\TtsSettingsRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Twig\Environment;

final class OverlayController
{
    public function __construct(
        private readonly Environment $twig,
        private readonly TtsSettingsRepository $settings,
    ) {
    }

    /** @param array{token:string} $args */
    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $settings = $this->settings->findByOverlayToken($args['token']);

        if ($settings === null) {
            $response->getBody()->write('Overlay not found.');
            return $response->withStatus(404);
        }

        $response->getBody()->write($this->twig->render('overlay.twig', [
            'token' => $args['token'],
            'channel' => $settings['channel'],
        ]));

        return $response;
    }

    /** @param array{token:string} $args */
    public function settings(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $settings = $this->settings->findByOverlayToken($args['token']);

        if ($settings === null) {
            $response->getBody()->write(json_encode(['error' => 'Overlay not found'], JSON_THROW_ON_ERROR));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('Cache-Control', 'no-store')
                ->withStatus(404);
        }

        $payload = [
            'version' => $settings['updated_at'],
            'channel' => $settings['channel'],
            'volume' => (float) $settings['volume'],
            'rate' => (float) $settings['rate'],
            'voiceName' => $settings['voice_name'],
            'announceChatter' => $settings['announce_chatter'],
            'modsOnly' => $settings['mods_only'],
            'vipsOnly' => $settings['vips_only'],
            'taggedOnly' => $settings['tagged_only'],
            'ignoreReplies' => $settings['ignore_replies'],
            'ignoreKnownBots' => $settings['ignore_known_bots'],
            'excludeCommands' => $settings['exclude_commands'],
            'excludeLinks' => $settings['exclude_links'],
            'excludedChatters' => $settings['excluded_chatters'],
            'maxMessageLength' => (int) $settings['max_message_length'],
            'cooldownMs' => (int) $settings['cooldown_ms'],
        ];

        $response->getBody()->write(json_encode($payload, JSON_THROW_ON_ERROR));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Cache-Control', 'no-store');
    }
}