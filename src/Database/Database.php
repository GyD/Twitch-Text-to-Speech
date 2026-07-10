<?php

declare(strict_types=1);

namespace App\Database;

use App\Config\AppConfig;
use PDO;

final class Database
{
    public function __construct(
        private readonly string $rootPath,
        private readonly AppConfig $config,
    ) {
    }

    public function connect(): PDO
    {
        $databasePath = $this->config->databasePath();
        $databaseFile = str_starts_with($databasePath, '/') ? $databasePath : $this->rootPath . '/' . $databasePath;
        $databaseDir = dirname($databaseFile);

        if (!is_dir($databaseDir) && !mkdir($databaseDir, 0775, true) && !is_dir($databaseDir)) {
            throw new \RuntimeException(sprintf('Unable to create database directory: %s', $databaseDir));
        }

        if (!is_writable($databaseDir)) {
            throw new \RuntimeException(sprintf('Database directory is not writable: %s', $databaseDir));
        }

        if (!is_file($databaseFile) && false === touch($databaseFile)) {
            throw new \RuntimeException(sprintf('Unable to create database file: %s', $databaseFile));
        }

        if (!is_writable($databaseFile)) {
            throw new \RuntimeException(sprintf('Database file is not writable: %s', $databaseFile));
        }

        $pdo = new PDO('sqlite:' . $databaseFile);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys = ON');

        return $pdo;
    }
}