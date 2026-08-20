<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminTokenIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $plainTextToken = $request->bearerToken();

        if (! $plainTextToken) {
            return $next($request);
        }

        $accessToken = PersonalAccessToken::findToken($plainTextToken);

        if (! $accessToken) {
            return $next($request);
        }

        $idleMinutes = max(1, (int) config('appointment.admin_idle_timeout_minutes', 4320));
        $lastActivity = $accessToken->last_used_at ?? $accessToken->created_at;

        if ($lastActivity && $lastActivity->lt(now()->subMinutes($idleMinutes))) {
            $accessToken->delete();

            return new JsonResponse([
                'message' => 'A munkameneted inaktivitás miatt lejárt. Jelentkezz be újra.',
                'code' => 'TOKEN_IDLE_EXPIRED',
            ], 401);
        }

        return $next($request);
    }
}
