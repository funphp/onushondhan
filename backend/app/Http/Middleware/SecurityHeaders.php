<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $headers = $response->headers;

        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), interest-cohort=()');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');

        if ($request->is('api/*')) {
            // API responses (including file downloads) are never rendered as documents.
            $headers->set('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'; sandbox");
            if (! $headers->has('Cache-Control') || $headers->get('Cache-Control') === 'no-cache, private') {
                $headers->set('Cache-Control', 'no-store, private');
            }
        } elseif (! $headers->has('Content-Security-Policy')) {
            // Filament admin (Livewire/Alpine) needs inline styles/scripts; still block framing/objects.
            $headers->set('Content-Security-Policy', "frame-ancestors 'none'; object-src 'none'; base-uri 'self'; form-action 'self'");
        }

        if ($request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
