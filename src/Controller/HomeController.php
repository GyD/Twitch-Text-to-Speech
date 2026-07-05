<?php

declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Twig\Environment;

final class HomeController
{
    public function __construct(private readonly Environment $twig)
    {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $response->getBody()->write($this->twig->render('home.twig', [
            'isAuthenticated' => !empty($_SESSION['user_id']),
            'appUrl' => rtrim($_ENV['APP_URL'] ?? '', '/'),
        ]));

        return $response;
    }
}