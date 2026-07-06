<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$basePath = strstr($scriptName, '/backend/public/index.php', true) ?: '';

if ($basePath !== '' && str_starts_with($requestUri, $basePath.'/api/')) {
    $_SERVER['REQUEST_URI'] = substr($requestUri, strlen($basePath));
}

if (empty($_SERVER['HTTP_AUTHORIZATION']) && ! empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
    $_SERVER['HTTP_AUTHORIZATION'] = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
}

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());