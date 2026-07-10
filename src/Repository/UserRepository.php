<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

final class UserRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM users WHERE id = :id');
        $statement->execute(['id' => $id]);
        $user = $statement->fetch();

        return $user ?: null;
    }

    /** @param array<string, mixed> $profile */
    public function upsertFromTwitchProfile(array $profile): int
    {
        $twitchId = $profile['id'] ?? null;
        $login = $profile['login'] ?? null;

        if (!is_string($twitchId) || $twitchId === '' || !is_string($login) || $login === '') {
            throw new \InvalidArgumentException('Twitch profile is missing required identity fields.');
        }

        $now = gmdate(DATE_ATOM);

        $statement = $this->pdo->prepare(
            'INSERT INTO users (twitch_id, login, display_name, profile_image_url, created_at, updated_at)
             VALUES (:twitch_id, :login, :display_name, :profile_image_url, :created_at, :updated_at)
             ON CONFLICT(twitch_id) DO UPDATE SET
                login = excluded.login,
                display_name = excluded.display_name,
                profile_image_url = excluded.profile_image_url,
                updated_at = excluded.updated_at'
        );

        $statement->execute([
            'twitch_id' => $twitchId,
            'login' => $login,
            'display_name' => $this->optionalString($profile['display_name'] ?? null) ?? $login,
            'profile_image_url' => $this->optionalString($profile['profile_image_url'] ?? null),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $lookup = $this->pdo->prepare('SELECT id FROM users WHERE twitch_id = :twitch_id');
        $lookup->execute(['twitch_id' => $twitchId]);

        return (int) $lookup->fetchColumn();
    }

    private function optionalString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}