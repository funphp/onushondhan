<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\RequestId;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AssignRequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $incoming = (string) $request->headers->get('X-Request-Id', '');
        // Only accept well-formed ids from upstream proxies; otherwise mint one.
        $id = preg_match('/^[A-Za-z0-9\-_.]{8,64}$/', $incoming) === 1 ? $incoming : null;
        RequestId::reset();
        if ($id !== null) {
            RequestId::set($id);
        }
        $id = RequestId::get();
        Log::withContext(['request_id' => $id]);

        $response = $next($request);
        $response->headers->set('X-Request-Id', $id);

        return $response;
    }
}
