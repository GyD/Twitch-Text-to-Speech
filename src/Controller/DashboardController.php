<?php

declare(strict_types=1);

namespace App\Controller;

use App\Config\AppConfig;
use App\Repository\TtsSettingsRepository;
use App\Repository\UserRepository;
use App\Security\CsrfTokenManager;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Twig\Environment;

final class DashboardController
{
    public function __construct(
        private readonly Environment $twig,
        private readonly UserRepository $users,
        private readonly TtsSettingsRepository $settings,
        private readonly AppConfig $config,
        private readonly CsrfTokenManager $csrf,
    ) {
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = (int) $_SESSION['user_id'];
        $user = $this->users->find($userId);

        if ($user === null) {
            unset($_SESSION['user_id']);

            return $response->withHeader('Location', '/')->withStatus(302);
        }

        $settings = $this->settings->getOrCreateForUser($userId, (string) ($user['login'] ?? ''));
        $appUrl = $this->config->appUrl();

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
        $data = $this->parsedBody($request);

        if ($data === null || !$this->csrf->isTokenValid($data)) {
            $response->getBody()->write('Invalid CSRF token.');
            return $response->withStatus(400);
        }

        try {
            $this->settings->saveForUser((int) $_SESSION['user_id'], [
                'channel' => $data['channel'] ?? '',
                'volume' => $data['volume'] ?? 1,
                'rate' => $data['rate'] ?? 1,
                'voice_name' => $data['voice_name'] ?? '',
                'announce_chatter' => $data['announce_chatter'] ?? false,
                'mods_only' => $data['mods_only'] ?? false,
                'vips_only' => $data['vips_only'] ?? false,
                'tagged_only' => $data['tagged_only'] ?? false,
                'ignore_replies' => $data['ignore_replies'] ?? false,
                'ignore_leading_mentions' => $data['ignore_leading_mentions'] ?? false,
                'ignore_known_bots' => $data['ignore_known_bots'] ?? false,
                'ignore_streamer' => $data['ignore_streamer'] ?? false,
                'ignore_emotes' => $data['ignore_emotes'] ?? false,
                'exclude_commands' => $data['exclude_commands'] ?? false,
                'exclude_links' => $data['exclude_links'] ?? false,
                'excluded_chatters' => $data['excluded_chatters'] ?? '',
                'max_message_length' => $data['max_message_length'] ?? 250,
                'cooldown_ms' => $data['cooldown_ms'] ?? 1000,
            ]);
        } catch (InvalidArgumentException $exception) {
            $_SESSION['flash'] = $exception->getMessage();

            return $response->withHeader('Location', '/dashboard')->withStatus(302);
        }

        $_SESSION['flash'] = 'TTS preferences saved.';

        return $response->withHeader('Location', '/dashboard')->withStatus(302);
    }

    /** @return array<string, mixed>|null */
    private function parsedBody(ServerRequestInterface $request): ?array
    {
        $body = $request->getParsedBody();

        return is_array($body) ? $body : null;
    }
}