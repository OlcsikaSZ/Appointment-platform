<?php
declare(strict_types=1);

function apply_security_headers(): void
{
    if (headers_sent()) {
        return;
    }

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()');
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob: https:; font-src 'self' data:; connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'");

    $forwardedProto = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    $https = (! empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') || $forwardedProto === 'https';
    if ($https) {
        header('Strict-Transport-Security: max-age=31536000');
    }
}

apply_security_headers();

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
        'account' => '/fiokom',
        'privacy' => '/adatkezeles',
        'terms' => '/felhasznalasi-feltetelek',
        'imprint' => '/impresszum',
        'cookies' => '/suti-tajekoztato',
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
    '/fiokom' => 'account',
    '/adatkezeles' => 'legal',
    '/felhasznalasi-feltetelek' => 'legal',
    '/impresszum' => 'legal',
    '/suti-tajekoztato' => 'legal',
];

$legalDocuments = [
    '/adatkezeles' => [
        'type' => 'privacy',
        'eyebrow' => 'Adatvédelem',
        'title' => 'Adatkezelési tájékoztató',
        'field' => 'privacyPolicy',
    ],
    '/felhasznalasi-feltetelek' => [
        'type' => 'terms',
        'eyebrow' => 'Jogi dokumentum',
        'title' => 'Felhasználási feltételek',
        'field' => 'termsText',
    ],
    '/impresszum' => [
        'type' => 'imprint',
        'eyebrow' => 'Kapcsolati és szolgáltatói adatok',
        'title' => 'Impresszum',
        'field' => 'imprintText',
    ],
    '/suti-tajekoztato' => [
        'type' => 'cookies',
        'eyebrow' => 'Technikai tárolás',
        'title' => 'Süti- és technikai tárolási tájékoztató',
        'field' => 'cookiePolicy',
    ],
];

$knownRoute = array_key_exists($relativePath, $viewRoutes);
$currentView = $knownRoute ? $viewRoutes[$relativePath] : 'not-found';
$legalDocument = $legalDocuments[$relativePath] ?? null;

if (! $knownRoute) {
    http_response_code(404);
}

if ($currentView === 'manage') {
    header('Referrer-Policy: no-referrer');
    header('Cache-Control: no-store, private');
    header('Pragma: no-cache');
}

$viewFile = __DIR__.'/views/'.$currentView.'/index.php';

if (! is_file($viewFile)) {
    http_response_code(404);
    echo 'A keresett nezet nem talalhato.';
    exit;
}

require $viewFile;
