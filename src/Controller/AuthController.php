<?php

declare(strict_types=1);

namespace App\Controller;

use App\Auth\TwitchAuthService;
use App\Repository\TtsSettingsRepository;
use App\Repository\UserRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Response;

final class AuthController
{
    public function __construct(
        private readonly TwitchAuthService $twitchAuth,
        private readonly UserRepository $users,
        private readonly TtsSettingsRepository $settings,
    ) {
    }

    public function login(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $state = bin2hex(random_bytes(16));
        $_SESSION['oauth_state'] = $state;

        return $response
            ->withHeader('Location', $this->twitchAuth->getAuthorizationUrl($state))
            ->withStatus(302);
    }

    public function callback(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $state = $params['state'] ?? '';
        $code = $params['code'] ?? '';

        if ($state === '' || !hash_equals((string) ($_SESSION['oauth_state'] ?? ''), (string) $state)) {
            $response->getBody()->write('Invalid OAuth state.');
            return $response->withStatus(400);
        }

        if (!is_string($code) || $code === '') {
            $response->getBody()->write('Missing OAuth code.');
            return $response->withStatus(400);
        }

        $profile = $this->twitchAuth->fetchUserProfile($code);
        $userId = $this->users->upsertFromTwitchProfile($profile);

        $_SESSION['user_id'] = $userId;
        unset($_SESSION['oauth_state']);

        $this->settings->getOrCreateForUser($userId, (string) ($profile['login'] ?? ''));

        return $response->withHeader('Location', '/dashboard')->withStatus(302);
    }

    public function logout(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $_SESSION = [];

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        return (new Response(302))->withHeader('Location', '/');
    }
}