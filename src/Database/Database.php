<?php

declare(strict_types=1);

namespace App\Database;

use PDO;

final class Database
{
    public function __construct(private readonly string $rootPath)
    {
    }

    public function connect(): PDO
    {
        $databasePath = $_ENV['DATABASE_PATH'] ?? 'var/app.sqlite';
        $databaseFile = str_starts_with($databasePath, '/') ? $databasePath : $this->rootPath . '/' . $databasePath;
        $databaseDir = dirname($databaseFile);

        if (!is_dir($databaseDir)) {
            mkdir($databaseDir, 0775, true);
        }

        $pdo = new PDO('sqlite:' . $databaseFile);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys = ON');

        return $pdo;
    }
}