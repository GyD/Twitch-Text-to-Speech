<?php

declare(strict_types=1);

namespace App\Auth;

use App\Config\AppConfig;
use RuntimeException;

final class TwitchAuthService
{
    private string $clientId;
    private string $clientSecret;
    private string $redirectUri;

    public function __construct(AppConfig $config)
    {
        $this->clientId = $config->twitchClientId();
        $this->clientSecret = $config->twitchClientSecret();
        $this->redirectUri = $config->twitchRedirectUri();
    }

    public function getAuthorizationUrl(string $state): string
    {
        $this->assertConfigured();

        return 'https://id.twitch.tv/oauth2/authorize?' . http_build_query([
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
            'scope' => 'user:read:email',
            'state' => $state,
        ]);
    }

    /** @return array<string, mixed> */
    public function fetchUserProfile(string $code): array
    {
        $this->assertConfigured();

        $tokenData = $this->requestJson('https://id.twitch.tv/oauth2/token', [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query([
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'code' => $code,
                'grant_type' => 'authorization_code',
                'redirect_uri' => $this->redirectUri,
            ]),
        ]);

        $accessToken = $tokenData['access_token'] ?? null;

        if (!is_string($accessToken) || $accessToken === '') {
            throw new RuntimeException('Twitch did not return an access token.');
        }

        $userData = $this->requestJson('https://api.twitch.tv/helix/users', [
            'method' => 'GET',
            'header' => implode("\r\n", [
                'Authorization: Bearer ' . $accessToken,
                'Client-Id: ' . $this->clientId,
            ]) . "\r\n",
        ]);

        if (empty($userData['data'][0]) || !is_array($userData['data'][0])) {
            throw new RuntimeException('Unable to fetch Twitch user profile.');
        }

        return $userData['data'][0];
    }

    private function assertConfigured(): void
    {
        if ($this->clientId === '' || $this->clientSecret === '' || $this->redirectUri === '') {
            throw new RuntimeException('Twitch OAuth is not configured. Please check config/settings.local.php.');
        }
    }

    /** @param array<string, string> $options @return array<string, mixed> */
    private function requestJson(string $url, array $options): array
    {
        $response = file_get_contents($url, false, stream_context_create(['http' => $options]));

        if ($response === false) {
            throw new RuntimeException('Twitch HTTP request failed.');
        }

        $decoded = json_decode($response, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($decoded)) {
            throw new RuntimeException('Twitch returned an invalid JSON response.');
        }

        return $decoded;
    }
}