<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->hasVerifiedEmail() !== true) {
            throw new ApiException('Please verify your email address first.', 'email_unverified', 403);
        }

        return $next($request);
    }
}
