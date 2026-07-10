<?php

declare(strict_types=1);

use App\Config\AppConfig;

require __DIR__ . '/../vendor/autoload.php';

$rootPath = dirname(__DIR__);
$config = AppConfig::load($rootPath);

$databasePath = $config->databasePath();
$databaseFile = str_starts_with($databasePath, '/') ? $databasePath : $rootPath . '/' . $databasePath;
$databaseDir = dirname($databaseFile);

if (!is_dir($databaseDir)) {
    mkdir($databaseDir, 0775, true);
}

$pdo = new PDO('sqlite:' . $databaseFile);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('PRAGMA foreign_keys = ON');

$schema = file_get_contents($rootPath . '/database/schema.sql');

if ($schema === false) {
    throw new RuntimeException('Unable to read database schema file.');
}

$pdo->exec($schema);

$tableInfoStatement = $pdo->query('PRAGMA table_info(tts_settings)');

if ($tableInfoStatement === false) {
    throw new RuntimeException('Unable to read tts_settings table information.');
}

$columns = $tableInfoStatement->fetchAll(PDO::FETCH_ASSOC);
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

if (!in_array('ignore_leading_mentions', $columnNames, true)) {
    $pdo->exec('ALTER TABLE tts_settings ADD COLUMN ignore_leading_mentions INTEGER NOT NULL DEFAULT 0');
}

if (!in_array('ignore_known_bots', $columnNames, true)) {
    $pdo->exec('ALTER TABLE tts_settings ADD COLUMN ignore_known_bots INTEGER NOT NULL DEFAULT 1');
}

if (!in_array('ignore_streamer', $columnNames, true)) {
    $pdo->exec('ALTER TABLE tts_settings ADD COLUMN ignore_streamer INTEGER NOT NULL DEFAULT 1');
}

if (!in_array('ignore_emotes', $columnNames, true)) {
    $pdo->exec('ALTER TABLE tts_settings ADD COLUMN ignore_emotes INTEGER NOT NULL DEFAULT 1');
}

echo sprintf("SQLite database migrated: %s\n", $databaseFile);