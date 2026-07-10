<?php

declare(strict_types=1);

namespace App\Controller;

use App\Auth\TwitchAuthService;
use App\Repository\TtsSettingsRepository;
use App\Repository\UserRepository;
use App\Security\CsrfTokenManager;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class AuthController
{
    public function __construct(
        private readonly TwitchAuthService $twitchAuth,
        private readonly UserRepository $users,
        private readonly TtsSettingsRepository $settings,
        private readonly CsrfTokenManager $csrf,
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

        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        unset($_SESSION['oauth_state']);

        $this->settings->getOrCreateForUser($userId, (string) ($profile['login'] ?? ''));

        return $response->withHeader('Location', '/dashboard')->withStatus(302);
    }

    public function logout(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!$this->csrf->isTokenValid($this->parsedBody($request))) {
            $response->getBody()->write('Invalid CSRF token.');
            return $response->withStatus(400);
        }

        $_SESSION = [];

        if (session_status() === PHP_SESSION_ACTIVE) {
            $params = session_get_cookie_params();
            $sessionName = session_name();

            if ($sessionName !== false) {
                setcookie($sessionName, '', [
                    'expires' => time() - 42000,
                    'path' => $params['path'],
                    'domain' => $params['domain'],
                    'secure' => $params['secure'],
                    'httponly' => $params['httponly'],
                    'samesite' => $params['samesite'],
                ]);
            }

            session_destroy();
        }

        return $response->withHeader('Location', '/')->withStatus(302);
    }

    /** @return array<string, mixed>|null */
    private function parsedBody(ServerRequestInterface $request): ?array
    {
        $body = $request->getParsedBody();

        return is_array($body) ? $body : null;
    }
}