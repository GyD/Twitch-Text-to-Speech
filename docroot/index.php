<?php

declare(strict_types=1);

use App\Auth\TwitchAuthService;
use App\Controller\AuthController;
use App\Controller\DashboardController;
use App\Controller\HomeController;
use App\Controller\OverlayController;
use App\Config\AppConfig;
use App\Database\Database;
use App\Middleware\AuthMiddleware;
use App\Repository\TtsSettingsRepository;
use App\Repository\UserRepository;
use Slim\Factory\AppFactory;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

require __DIR__ . '/../vendor/autoload.php';

$rootPath = dirname(__DIR__);
$config = AppConfig::load($rootPath);

session_name('twitch_tts_session');
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
    'cookie_secure' => str_starts_with($config->appUrl(), 'https://'),
]);

$app = AppFactory::create();
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();
$app->addErrorMiddleware($config->appEnv() !== 'prod', true, true);

$twigCache = $config->twigCache();

if (is_string($twigCache) && !str_starts_with($twigCache, '/')) {
    $twigCache = $rootPath . '/' . ltrim($twigCache, '/');
}

$twig = new Environment(new FilesystemLoader($rootPath . '/templates'), [
    'cache' => $twigCache,
    'auto_reload' => $config->appEnv() !== 'prod',
    'strict_variables' => true,
]);
$twig->addGlobal('appVersion', $config->appVersion());

$pdo = (new Database($rootPath, $config))->connect();
$users = new UserRepository($pdo);
$settings = new TtsSettingsRepository($pdo);

$home = new HomeController($twig, $config);
$auth = new AuthController(new TwitchAuthService($config), $users, $settings);
$dashboard = new DashboardController($twig, $users, $settings, $config);
$overlay = new OverlayController($twig, $settings, $config);
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