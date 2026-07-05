<?php

declare(strict_types=1);

use Dotenv\Dotenv;

require __DIR__ . '/../vendor/autoload.php';

$rootPath = dirname(__DIR__);

if (file_exists($rootPath . '/.env')) {
    Dotenv::createImmutable($rootPath)->safeLoad();
}

$databasePath = $_ENV['DATABASE_PATH'] ?? 'var/app.sqlite';
$databaseFile = str_starts_with($databasePath, '/') ? $databasePath : $rootPath . '/' . $databasePath;
$databaseDir = dirname($databaseFile);

if (!is_dir($databaseDir)) {
    mkdir($databaseDir, 0775, true);
}

$pdo = new PDO('sqlite:' . $databaseFile);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('PRAGMA foreign_keys = ON');
$pdo->exec(file_get_contents($rootPath . '/database/schema.sql'));

$columns = $pdo->query('PRAGMA table_info(tts_settings)')->fetchAll(PDO::FETCH_ASSOC);
$columnNames = array_column($columns, 'name');

if (!in_array('vips_only', $columnNames, true)) {
    $pdo->exec('ALTER TABLE tts_settings ADD COLUMN vips_only INTEGER NOT NULL DEFAULT 0');
}

if (!in_array('rate', $columnNames, true)) {
    $pdo->exec('ALTER TABLE tts_settings ADD COLUMN rate REAL NOT NULL DEFAULT 1');
}

if (!in_array('ignore_replies', $columnNames, true)) {
    $pdo->exec('ALTER TABLE tts_settings ADD COLUMN ignore_replies INTEGER NOT NULL DEFAULT 0');
}

if (!in_array('ignore_known_bots', $columnNames, true)) {
    $pdo->exec('ALTER TABLE tts_settings ADD COLUMN ignore_known_bots INTEGER NOT NULL DEFAULT 1');
}

echo sprintf("SQLite database migrated: %s\n", $databaseFile);