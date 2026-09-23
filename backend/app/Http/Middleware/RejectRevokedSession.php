<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\SessionRevoker;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Treats requests carrying a revoked session id as guests with a fresh session. */
class RejectRevokedSession
{
    public function __construct(private readonly SessionRevoker $revoker) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->hasSession() && $this->revoker->isRevoked($request->session()->getId())) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            Auth::forgetGuards();
        }

        return $next($request);
    }
}
