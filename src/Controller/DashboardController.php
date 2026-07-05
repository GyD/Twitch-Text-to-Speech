<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\TtsSettingsRepository;
use App\Repository\UserRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Twig\Environment;

final class DashboardController
{
    public function __construct(
        private readonly Environment $twig,
        private readonly UserRepository $users,
        private readonly TtsSettingsRepository $settings,
    ) {
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = (int) $_SESSION['user_id'];
        $user = $this->users->find($userId);
        $settings = $this->settings->getOrCreateForUser($userId, (string) ($user['login'] ?? ''));
        $appUrl = rtrim($_ENV['APP_URL'] ?? '', '/');

        $response->getBody()->write($this->twig->render('dashboard.twig', [
            'isAuthenticated' => true,
            'user' => $user,
            'settings' => $settings,
            'excludedChattersText' => implode("\n", $settings['excluded_chatters'] ?? []),
            'overlayUrl' => $appUrl . '/overlay/' . $settings['overlay_token'],
            'flash' => $_SESSION['flash'] ?? null,
        ]));

        unset($_SESSION['flash']);

        return $response;
    }

    public function save(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = (array) $request->getParsedBody();

        $this->settings->saveForUser((int) $_SESSION['user_id'], [
            'channel' => $data['channel'] ?? '',
            'volume' => $data['volume'] ?? 1,
            'voice_name' => $data['voice_name'] ?? '',
            'announce_chatter' => $data['announce_chatter'] ?? false,
            'mods_only' => $data['mods_only'] ?? false,
            'vips_only' => $data['vips_only'] ?? false,
            'tagged_only' => $data['tagged_only'] ?? false,
            'exclude_commands' => $data['exclude_commands'] ?? false,
            'exclude_links' => $data['exclude_links'] ?? false,
            'excluded_chatters' => $data['excluded_chatters'] ?? '',
            'max_message_length' => $data['max_message_length'] ?? 250,
            'cooldown_ms' => $data['cooldown_ms'] ?? 1000,
        ]);

        $_SESSION['flash'] = 'Préférences TTS sauvegardées.';

        return $response->withHeader('Location', '/dashboard')->withStatus(302);
    }
}