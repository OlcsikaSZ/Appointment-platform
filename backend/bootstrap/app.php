<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use App\Http\Middleware\EnsureAdminTokenIsActive;
use App\Http\Middleware\EnsureAdminAccess;
use App\Http\Middleware\EnsureCustomerAccess;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands()
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(SecurityHeaders::class);

        $middleware->alias([
            'admin.token.active' => EnsureAdminTokenIsActive::class,
            'admin.access' => EnsureAdminAccess::class,
            'customer.access' => EnsureCustomerAccess::class,
        ]);

        // A Sanctum hitelesítés frissíti a token last_used_at mezőjét.
        // Ezért az inaktivitás-ellenőrzésnek kötelezően az auth middleware előtt kell futnia.
        $middleware->prependToPriorityList(
            AuthenticatesRequests::class,
            EnsureAdminTokenIsActive::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
