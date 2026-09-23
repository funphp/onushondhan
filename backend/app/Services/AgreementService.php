<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AgreementType;
use App\Exceptions\ApiException;
use App\Models\TermsAcceptance;
use App\Models\TermsVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;

class AgreementService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @return list<string> */
    public function requiredTypesFor(User $user): array
    {
        $types = config('onushondhan.agreements.user');
        if ($user->investigator !== null) {
            $types = array_merge($types, config('onushondhan.agreements.investigator'));
        }

        return $types;
    }

    /** @return list<TermsVersion> current versions the user has not yet accepted */
    public function pendingFor(User $user): array
    {
        return $this->pendingForTypes($user, $this->requiredTypesFor($user));
    }

    /**
     * @param  list<string>  $types
     * @return list<TermsVersion>
     */
    public function pendingForTypes(User $user, array $types): array
    {
        $pending = [];
        foreach ($types as $type) {
            $current = TermsVersion::currentFor($type);
            if ($current === null) {
                continue; // Nothing published yet for this type — nothing to accept.
            }
            $accepted = TermsAcceptance::query()
                ->where('user_id', $user->id)
                ->where('terms_version_id', $current->id)
                ->exists();
            if (! $accepted) {
                $pending[] = $current;
            }
        }

        return $pending;
    }

    /**
     * Accept the given current versions. Each id must be the *current* published version
     * of its type, so a stale client cannot record acceptance of a superseded text.
     *
     * @param  list<int>  $versionIds
     * @return list<TermsAcceptance>
     */
    public function accept(User $user, array $versionIds, string $context = 'explicit'): array
    {
        return DB::transaction(function () use ($user, $versionIds, $context): array {
            $out = [];
            foreach (array_unique($versionIds) as $id) {
                $version = TermsVersion::query()->findOrFail($id);
                $current = TermsVersion::currentFor($version->agreement_type);
                if ($current === null || $current->id !== $version->id) {
                    throw new ApiException('This agreement version is no longer current. Please reload and try again.', 'agreement_outdated', 409);
                }
                $acceptance = TermsAcceptance::query()->firstOrCreate(
                    ['user_id' => $user->id, 'terms_version_id' => $version->id],
                    [
                        'agreement_type' => $version->agreement_type->value,
                        'accepted_at' => now(),
                        'ip_address' => Request::ip(),
                        'user_agent' => mb_substr((string) Request::userAgent(), 0, 500),
                        'metadata_json' => ['context' => $context, 'version' => $version->version],
                        'created_at' => now(),
                    ],
                );
                if ($acceptance->wasRecentlyCreated) {
                    $this->audit->log('agreement.accepted', $acceptance, null, [
                        'agreement_type' => $version->agreement_type->value,
                        'version' => $version->version,
                        'context' => $context,
                    ], $user);
                }
                $out[] = $acceptance;
            }

            return $out;
        });
    }

    /**
     * Accept the current versions for the given types (used during registration/onboarding
     * where the client submits explicit consent checkboxes).
     *
     * @param  list<string>  $types
     */
    public function acceptCurrent(User $user, array $types, string $context): void
    {
        $ids = [];
        foreach ($types as $type) {
            $current = TermsVersion::currentFor($type);
            if ($current !== null) {
                $ids[] = $current->id;
            }
        }
        $this->accept($user, $ids, $context);
    }

    /** @return array<string, TermsVersion|null> */
    public function currentVersions(): array
    {
        $out = [];
        foreach (AgreementType::cases() as $type) {
            $out[$type->value] = TermsVersion::currentFor($type);
        }

        return $out;
    }
}
