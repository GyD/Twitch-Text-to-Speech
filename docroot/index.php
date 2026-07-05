<?php

declare(strict_types=1);

use App\Auth\TwitchAuthService;
use App\Controller\AuthController;
use App\Controller\DashboardController;
use App\Controller\HomeController;
use App\Controller\OverlayController;
use App\Database\Database;
use App\Middleware\AuthMiddleware;
use App\Repository\TtsSettingsRepository;
use App\Repository\UserRepository;
use Dotenv\Dotenv;
use Slim\Factory\AppFactory;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

require __DIR__ . '/../vendor/autoload.php';

$rootPath = dirname(__DIR__);

if (file_exists($rootPath . '/.env')) {
    Dotenv::createImmutable($rootPath)->safeLoad();
}

session_name('twitch_tts_session');
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
    'cookie_secure' => str_starts_with($_ENV['APP_URL'] ?? '', 'https://'),
]);

$app = AppFactory::create();
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();
$app->addErrorMiddleware(($_ENV['APP_ENV'] ?? 'prod') !== 'prod', true, true);

$twig = new Environment(new FilesystemLoader($rootPath . '/templates'), [
    'cache' => false,
    'strict_variables' => true,
]);

$pdo = (new Database($rootPath))->connect();
$users = new UserRepository($pdo);
$settings = new TtsSettingsRepository($pdo);

$home = new HomeController($twig);
$auth = new AuthController(new TwitchAuthService(), $users, $settings);
$dashboard = new DashboardController($twig, $users, $settings);
$overlay = new OverlayController($twig, $settings);
$authMiddleware = new AuthMiddleware();

$app->get('/', $home);
$app->get('/login', [$auth, 'login']);
$app->get('/auth/twitch/callback', [$auth, 'callback']);
$app->post('/logout', [$auth, 'logout']);

$app->get('/dashboard', [$dashboard, 'show'])->add($authMiddleware);
$app->post('/dashboard/settings', [$dashboard, 'save'])->add($authMiddleware);

$app->get('/overlay/{token}', [$overlay, 'show']);
$app->get('/api/overlay/{token}', [$overlay, 'settings']);

$app->run();