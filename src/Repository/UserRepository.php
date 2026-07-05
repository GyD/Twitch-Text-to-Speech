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
            'twitch_id' => $profile['id'],
            'login' => $profile['login'],
            'display_name' => $profile['display_name'] ?? $profile['login'],
            'profile_image_url' => $profile['profile_image_url'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $lookup = $this->pdo->prepare('SELECT id FROM users WHERE twitch_id = :twitch_id');
        $lookup->execute(['twitch_id' => $profile['id']]);

        return (int) $lookup->fetchColumn();
    }
}