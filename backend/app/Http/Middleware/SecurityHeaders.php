<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $headers = $response->headers;

        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'SAMEORIGIN');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');
        $headers->set(
            'Content-Security-Policy',
            "default-src 'self'; "
            ."script-src 'self' 'unsafe-eval'; "
            ."style-src 'self' 'unsafe-inline'; "
            ."img-src 'self' data: blob: https:; "
            ."font-src 'self' data:; "
            ."connect-src 'self'; "
            ."object-src 'none'; "
            ."base-uri 'self'; "
            ."form-action 'self'; "
            ."frame-ancestors 'self'"
        );

        if (! $headers->has('Referrer-Policy')) {
            $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        }

        if ($request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        return $response;
    }
}
