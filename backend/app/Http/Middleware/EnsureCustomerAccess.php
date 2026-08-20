<?php

namespace App\Http\Middleware;

use App\Models\CustomerAccount;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCustomerAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $account = $request->user();
        abort_unless($account instanceof CustomerAccount && $account->tokenCan('user'), 403, 'Ehhez ügyfélfiók szükséges.');
        abort_unless($account->role === 'user' && $account->email_verified_at, 403, 'Az ügyfélfiók nincs megerősítve.');
        abort_unless($account->business?->active, 403, 'A vállalkozás jelenleg inaktív.');

        return $next($request);
    }
}
