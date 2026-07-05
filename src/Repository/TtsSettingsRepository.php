<?php

declare(strict_types=1);

namespace App\Repository;

use PDO;

final class TtsSettingsRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array<string, mixed>|null */
    public function findByUserId(int $userId): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM tts_settings WHERE user_id = :user_id');
        $statement->execute(['user_id' => $userId]);
        $settings = $statement->fetch();

        return $settings ? $this->normalize($settings) : null;
    }

    /** @return array<string, mixed>|null */
    public function findByOverlayToken(string $token): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT tts_settings.*, users.login AS owner_login
             FROM tts_settings
             INNER JOIN users ON users.id = tts_settings.user_id
             WHERE overlay_token = :token'
        );
        $statement->execute(['token' => $token]);
        $settings = $statement->fetch();

        return $settings ? $this->normalize($settings) : null;
    }

    /** @return array<string, mixed> */
    public function getOrCreateForUser(int $userId, string $defaultChannel): array
    {
        $settings = $this->findByUserId($userId);

        if ($settings !== null) {
            return $settings;
        }

        $now = gmdate(DATE_ATOM);
        $token = bin2hex(random_bytes(24));

        $statement = $this->pdo->prepare(
            'INSERT INTO tts_settings (
                user_id, channel, overlay_token, created_at, updated_at
             ) VALUES (
                :user_id, :channel, :overlay_token, :created_at, :updated_at
             )'
        );
        $statement->execute([
            'user_id' => $userId,
            'channel' => $defaultChannel,
            'overlay_token' => $token,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->findByUserId($userId) ?? [];
    }

    /** @param array<string, mixed> $data */
    public function saveForUser(int $userId, array $data): void
    {
        $current = $this->getOrCreateForUser($userId, (string) $data['channel']);
        $now = gmdate(DATE_ATOM);

        $statement = $this->pdo->prepare(
            'UPDATE tts_settings SET
                channel = :channel,
                volume = :volume,
                rate = :rate,
                voice_name = :voice_name,
                announce_chatter = :announce_chatter,
                mods_only = :mods_only,
                vips_only = :vips_only,
                tagged_only = :tagged_only,
                ignore_replies = :ignore_replies,
                ignore_known_bots = :ignore_known_bots,
                exclude_commands = :exclude_commands,
                exclude_links = :exclude_links,
                excluded_chatters_json = :excluded_chatters_json,
                max_message_length = :max_message_length,
                cooldown_ms = :cooldown_ms,
                updated_at = :updated_at
             WHERE user_id = :user_id'
        );

        $statement->execute([
            'user_id' => $userId,
            'channel' => $this->sanitizeChannel((string) $data['channel']),
            'volume' => max(0, min(1, (float) $data['volume'])),
            'rate' => max(0.5, min(2, (float) $data['rate'])),
            'voice_name' => $data['voice_name'] ?: null,
            'announce_chatter' => !empty($data['announce_chatter']) ? 1 : 0,
            'mods_only' => !empty($data['mods_only']) ? 1 : 0,
            'vips_only' => !empty($data['vips_only']) ? 1 : 0,
            'tagged_only' => !empty($data['tagged_only']) ? 1 : 0,
            'ignore_replies' => !empty($data['ignore_replies']) ? 1 : 0,
            'ignore_known_bots' => !empty($data['ignore_known_bots']) ? 1 : 0,
            'exclude_commands' => !empty($data['exclude_commands']) ? 1 : 0,
            'exclude_links' => !empty($data['exclude_links']) ? 1 : 0,
            'excluded_chatters_json' => json_encode($this->parseExcludedChatters((string) $data['excluded_chatters']), JSON_THROW_ON_ERROR),
            'max_message_length' => max(1, min(500, (int) $data['max_message_length'])),
            'cooldown_ms' => max(0, min(30000, (int) $data['cooldown_ms'])),
            'updated_at' => $now,
        ]);
    }

    /**
     * @param array<string, mixed> $settings
     *
     * @return array<string, mixed>
     */
    private function normalize(array $settings): array
    {
        $settings['announce_chatter'] = (bool) $settings['announce_chatter'];
        $settings['mods_only'] = (bool) $settings['mods_only'];
        $settings['vips_only'] = (bool) ($settings['vips_only'] ?? false);
        $settings['tagged_only'] = (bool) $settings['tagged_only'];
        $settings['ignore_replies'] = (bool) ($settings['ignore_replies'] ?? false);
        $settings['ignore_known_bots'] = (bool) ($settings['ignore_known_bots'] ?? true);
        $settings['exclude_commands'] = (bool) $settings['exclude_commands'];
        $settings['exclude_links'] = (bool) $settings['exclude_links'];
        $settings['rate'] = (float) ($settings['rate'] ?? 1);
        $settings['excluded_chatters'] = json_decode($settings['excluded_chatters_json'] ?? '[]', true) ?: [];

        return $settings;
    }

    private function sanitizeChannel(string $channel): string
    {
        return ltrim(trim(strtolower($channel)), '#');
    }

    /** @return list<string> */
    private function parseExcludedChatters(string $value): array
    {
        $chatters = preg_split('/\R/', $value) ?: [];
        $chatters = array_map(static fn (string $chatter): string => strtolower(trim($chatter)), $chatters);
        $chatters = array_filter($chatters, static fn (string $chatter): bool => $chatter !== '');

        return array_values(array_unique($chatters));
    }
}