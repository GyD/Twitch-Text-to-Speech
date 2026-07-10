<?php

declare(strict_types=1);

namespace App\Security;

final class CsrfTokenManager
{
    private const SESSION_KEY = 'csrf_token';

    public function getToken(): string
    {
        $token = $_SESSION[self::SESSION_KEY] ?? null;

        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            $_SESSION[self::SESSION_KEY] = $token;
        }

        return $token;
    }

    /** @param array<string, mixed>|null $data */
    public function isTokenValid(?array $data): bool
    {
        $submittedToken = $data['_csrf_token'] ?? null;
        $sessionToken = $_SESSION[self::SESSION_KEY] ?? null;

        return is_string($submittedToken)
            && is_string($sessionToken)
            && $submittedToken !== ''
            && hash_equals($sessionToken, $submittedToken);
    }
}