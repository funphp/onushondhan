<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * The public API speaks Bangla by default. An explicit `X-Locale` header (bn|en)
 * overrides it; Accept-Language is deliberately ignored because many Bangladeshi
 * users browse with English browser settings but use a Bangla site.
 */
class SetApiLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $requested = strtolower((string) $request->headers->get('X-Locale', ''));
        $locale = in_array($requested, config('onushondhan.supported_locales'), true) ? $requested : (string) config('onushondhan.api_locale');
        App::setLocale($locale);

        $response = $next($request);
        $response->headers->set('Content-Language', $locale);

        return $response;
    }
}
