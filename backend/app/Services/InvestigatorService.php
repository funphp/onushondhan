<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\FileVault;
use App\Enums\IdentityMode;
use App\Exceptions\ApiException;
use App\Models\Investigator;
use App\Models\User;
use App\Services\Files\FileInspector;
use App\Services\Files\ImageSanitizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class InvestigatorService
{
    public function __construct(
        private readonly AgreementService $agreements,
        private readonly AuditLogger $audit,
        private readonly FileVault $vault,
        private readonly FileInspector $inspector,
        private readonly ImageSanitizer $sanitizer,
    ) {}

    /**
     * @param  array{public_name: string, bio?: string|null, website_url?: string|null, identity_mode: string, private_identity?: array<string, string|null>|null}  $data
     */
    public function onboard(User $user, array $data): Investigator
    {
        if ($user->investigator()->withTrashed()->exists()) {
            throw ApiException::conflict('You already have an investigator profile.', 'already_investigator');
        }
        if (! $user->hasVerifiedEmail()) {
            throw new ApiException('Verify your email address before becoming an investigator.', 'email_unverified', 403);
        }

        return DB::transaction(function () use ($user, $data): Investigator {
            $investigator = new Investigator;
            $investigator->fill([
                'public_name' => $data['public_name'],
                'bio' => $data['bio'] ?? null,
                'website_url' => $data['website_url'] ?? null,
                'identity_mode' => $data['identity_mode'],
            ]);
            $investigator->user_id = $user->id;
            $investigator->slug = Investigator::uniqueSlug($data['public_name']);
            $investigator->published_at = now();
            $investigator->save();

            $investigator->privateIdentity()->create($this->cleanPrivate($data['private_identity'] ?? []));

            $user->assignRole('investigator');
            $user->setRelation('investigator', $investigator);
            $this->agreements->acceptCurrent($user, config('onushondhan.agreements.investigator'), 'investigator_onboarding');

            $this->audit->log('investigator.onboarded', $investigator, null, [
                'identity_mode' => $investigator->identity_mode->value,
                'slug' => $investigator->slug,
            ], $user);

            return $investigator;
        });
    }

    /** @param array<string, mixed> $data */
    public function updateProfile(Investigator $investigator, array $data, User $actor): Investigator
    {
        return DB::transaction(function () use ($investigator, $data, $actor): Investigator {
            $before = $investigator->only(['public_name', 'slug', 'bio', 'website_url', 'identity_mode']);
            $investigator->fill(array_intersect_key($data, array_flip(['public_name', 'bio', 'website_url', 'identity_mode'])));

            if ($investigator->isDirty('public_name')) {
                // New slug and no redirect from the old one: a redirect would link an
                // earlier (possibly real) name to a newly protected identity.
                $investigator->slug = Investigator::uniqueSlug($investigator->public_name, $investigator->id);
            }
            $identityChanged = $investigator->isDirty(['public_name', 'identity_mode']);
            $investigator->save();

            $this->audit->log($identityChanged ? 'investigator.identity_changed' : 'investigator.profile_updated', $investigator, $before, $investigator->only(['public_name', 'slug', 'bio', 'website_url', 'identity_mode']), $actor);

            return $investigator;
        });
    }

    /** @param array<string, string|null> $data */
    public function updatePrivateIdentity(Investigator $investigator, array $data, User $actor): void
    {
        DB::transaction(function () use ($investigator, $data, $actor): void {
            $identity = $investigator->privateIdentity()->firstOrNew();
            $identity->fill($this->cleanPrivate($data));
            $identity->save();
            // Never write the private values themselves into the audit trail.
            $this->audit->log('identity.private_updated', $investigator, null, ['fields' => array_keys($this->cleanPrivate($data))], $actor);
        });
    }

    public function updateAvatar(Investigator $investigator, UploadedFile $file, User $actor): void
    {
        $inspected = $this->inspector->inspect($file, [
            'image/jpeg' => ['jpg', 'jpeg'], 'image/png' => ['png'], 'image/webp' => ['webp'],
        ], 5 * 1024 * 1024, 'avatar');
        $clean = $this->sanitizer->sanitize($inspected->path, $inspected->mime);
        if ($clean === null) {
            throw new ApiException('The image could not be processed.', 'image_unprocessable', 422, ['avatar' => ['The image could not be processed.']]);
        }
        try {
            $key = $this->vault->putFile($clean['path'], 'avatars');
        } finally {
            @unlink($clean['path']);
        }
        $old = $investigator->avatar_path;
        $investigator->forceFill(['avatar_path' => $key])->save();
        if ($old !== null && $this->vault->exists($old)) {
            $this->vault->delete($old);
        }
        $this->audit->log('investigator.avatar_updated', $investigator, null, null, $actor);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string|null>
     */
    private function cleanPrivate(array $data): array
    {
        $out = [];
        foreach (['legal_or_private_name', 'private_email', 'private_phone', 'notes'] as $key) {
            if (array_key_exists($key, $data)) {
                $value = $data[$key];
                $out[$key] = $value === null || $value === '' ? null : trim((string) $value);
            }
        }

        return $out;
    }

    public static function identityModes(): array
    {
        return IdentityMode::values();
    }
}
