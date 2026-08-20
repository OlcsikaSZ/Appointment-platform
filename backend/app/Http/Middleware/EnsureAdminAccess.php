<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->tokenCan('admin'), 403, 'Ehhez a művelethez admin jogosultság szükséges.');
        abort_unless(in_array($user->role, ['admin', 'owner'], true), 403, 'Ehhez a művelethez admin jogosultság szükséges.');
        abort_unless($user->business?->active, 403, 'A vállalkozás jelenleg inaktív.');

        return $next($request);
    }
}
