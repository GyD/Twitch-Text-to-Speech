<?php

declare(strict_types=1);

namespace App\Config;

final readonly class AppConfig
{
    /** @param array<string, mixed> $settings */
    private function __construct(private array $settings)
    {
    }

    public static function load(string $rootPath): self
    {
        $settings = self::loadFile($rootPath . '/config/settings.php');
        $localSettingsFile = $rootPath . '/config/settings.local.php';

        if (is_file($localSettingsFile)) {
            $settings = array_replace_recursive($settings, self::loadFile($localSettingsFile));
        }

        return new self($settings);
    }

    public function appEnv(): string
    {
        return $this->string('app.env', 'prod');
    }

    public function appUrl(): string
    {
        return rtrim($this->string('app.url'), '/');
    }

    public function databasePath(): string
    {
        return $this->string('database.path', 'var/app.sqlite');
    }

    public function twitchClientId(): string
    {
        return $this->string('twitch.client_id');
    }

    public function twitchClientSecret(): string
    {
        return $this->string('twitch.client_secret');
    }

    public function twitchRedirectUri(): string
    {
        return $this->string('twitch.redirect_uri');
    }

    private function string(string $path, string $default = ''): string
    {
        $value = $this->get($path);

        if ($value === null) {
            return $default;
        }

        return is_scalar($value) ? (string) $value : $default;
    }

    private function get(string $path): mixed
    {
        $value = $this->settings;

        foreach (explode('.', $path) as $key) {
            if (!is_array($value) || !array_key_exists($key, $value)) {
                return null;
            }

            $value = $value[$key];
        }

        return $value;
    }

    /** @return array<string, mixed> */
    private static function loadFile(string $file): array
    {
        $settings = require $file;

        if (!is_array($settings)) {
            throw new \RuntimeException(sprintf('Configuration file must return an array: %s', $file));
        }

        return $settings;
    }
}