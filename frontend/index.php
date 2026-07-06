<?php

declare(strict_types=1);

$basePath = dirname($_SERVER['SCRIPT_NAME'] ?? '');
$basePath = str_replace('\\', '/', $basePath);
$basePath = preg_replace('#/frontend$#', '', $basePath);
$basePath = rtrim($basePath, '/');

function app_base(): string
{
    global $basePath;

    return $basePath === '' ? '' : $basePath;
}

function asset(string $path): string
{
    return app_base().'/'.ltrim($path, '/');
}

function route_url(string $route = 'main'): string
{
    $routes = [
        'main' => '/',
        'admin' => '/admin',
        'manage' => '/manage',
    ];

    return app_base().($routes[$route] ?? '/');
}

function view_asset(string $path): string
{
    global $currentView;

    return asset('views/'.$currentView.'/'.ltrim($path, '/'));
}

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$relativePath = '/'.ltrim(substr($requestPath, strlen(app_base())), '/');
$relativePath = rtrim($relativePath, '/') ?: '/';

$viewRoutes = [
    '/' => 'main',
    '/index.php' => 'main',
    '/admin' => 'admin',
    '/admin.php' => 'admin',
    '/admin.html' => 'admin',
    '/manage' => 'manage',
    '/manage.php' => 'manage',
    '/manage.html' => 'manage',
];

$currentView = $viewRoutes[$relativePath] ?? 'main';
$viewFile = __DIR__.'/views/'.$currentView.'/index.php';

if (! is_file($viewFile)) {
    http_response_code(404);
    echo 'A keresett nezet nem talalhato.';
    exit;
}

require $viewFile;
