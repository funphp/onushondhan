<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use App\Services\AgreementService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks write actions until the user has accepted every current agreement that
 * applies to them (re-acceptance is required when a new version is published).
 */
class EnsureAgreementsAccepted
{
    public function __construct(private readonly AgreementService $agreements) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user !== null) {
            $pending = $this->agreements->pendingFor($user);
            if ($pending !== []) {
                throw new ApiException(
                    'You must accept the current platform agreements to continue.',
                    'agreements_required',
                    403,
                    ['agreements' => array_map(fn ($t) => $t->agreement_type->value, $pending)],
                );
            }
        }

        return $next($request);
    }
}
