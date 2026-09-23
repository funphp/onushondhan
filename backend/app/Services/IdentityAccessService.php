<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\Investigator;
use App\Models\User;

/** Staff access to an investigator's private identity: permission-gated, reason-required, audited. */
class IdentityAccessService
{
    public function __construct(private readonly AuditLogger $audit, private readonly SecurityLogger $security) {}

    /** @return array<string, string|null> */
    public function reveal(User $actor, Investigator $investigator, string $reason): array
    {
        if (! $actor->hasPermission('identity.view_private')) {
            throw ApiException::forbidden();
        }
        if (mb_strlen(trim($reason)) < 10) {
            throw new ApiException('A justification of at least 10 characters is required.', 'reason_required', 422, ['reason' => ['Explain why access is needed.']]);
        }
        $identity = $investigator->privateIdentity;
        $this->audit->log('identity.private_viewed', $investigator, null, ['reason' => mb_substr($reason, 0, 1000)], $actor);
        $this->security->event('identity.private_viewed', $actor, 'warning', ['investigator' => $investigator->public_id]);

        return [
            'account_name' => $investigator->user->name,
            'account_email' => $investigator->user->email,
            'legal_or_private_name' => $identity?->legal_or_private_name,
            'private_email' => $identity?->private_email,
            'private_phone' => $identity?->private_phone,
            'notes' => $identity?->notes,
        ];
    }
}
