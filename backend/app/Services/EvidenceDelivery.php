<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\FileVault;
use App\Enums\EvidenceAccessAction;
use App\Enums\EvidenceVisibility;
use App\Http\Resources\EvidenceResource;
use App\Models\Evidence;
use App\Models\EvidenceAccessLog;
use App\Models\User;
use App\Policies\EvidencePolicy;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The only path by which evidence bytes leave the server. Authorises, logs every
 * attempt (success or failure) and serves with safe headers.
 */
class EvidenceDelivery
{
    private const INLINE_KINDS = ['image', 'audio', 'video'];

    public function __construct(
        private readonly FileVault $vault,
        private readonly EvidencePolicy $policy,
        private readonly AuditLogger $audit,
        private readonly AnalyticsService $analytics,
    ) {}

    public function authorize(?User $user, Evidence $evidence, EvidenceAccessAction $action): bool
    {
        $allowed = $this->policy->access($user, $evidence);
        $this->log($evidence, $user, $action, $allowed, $allowed ? null : 'denied');

        return $allowed;
    }

    /** Short-lived link bound to the requesting user, usable by download managers / media players. */
    public function signedLink(Evidence $evidence, ?User $user, string $action = 'download'): string
    {
        $ttl = (int) config('onushondhan.evidence.download_link_ttl_minutes', 10);

        return URL::temporarySignedRoute(
            $action === 'preview' ? 'evidence.preview' : 'evidence.download',
            now()->addMinutes($ttl),
            ['evidence' => $evidence->public_id, 'uid' => $user->public_id ?? 'guest'],
            absolute: false,
        );
    }

    public function download(Evidence $evidence, ?User $user): Response
    {
        // Public (non-owner) viewers of images receive the metadata-stripped rendition.
        $useSanitized = $evidence->kind === 'image' && $evidence->preview_path !== null
            && ($user === null || ! $this->policy->isOwnerOrStaff($user, $evidence));
        $path = $useSanitized ? $evidence->preview_path : $evidence->storage_path;
        $mime = $useSanitized ? (string) ($evidence->metadata_json['preview_mime'] ?? 'image/jpeg') : $evidence->mime_type;
        $name = $user !== null && $this->policy->isOwnerOrStaff($user, $evidence) ? $evidence->original_name : EvidenceResource::displayName($evidence);
        if ($useSanitized) {
            $name = pathinfo($name, PATHINFO_FILENAME).($mime === 'image/png' ? '.png' : '.jpg');
        }

        $this->afterSuccess($evidence, $user, EvidenceAccessAction::Download);

        return $this->serve($path, $mime, $name, 'attachment');
    }

    public function preview(Evidence $evidence, ?User $user): Response
    {
        if ($evidence->kind === 'image') {
            abort_if($evidence->preview_path === null, 404);
            $this->afterSuccess($evidence, $user, EvidenceAccessAction::Preview);

            return $this->serve($evidence->preview_path, (string) ($evidence->metadata_json['preview_mime'] ?? 'image/jpeg'), 'preview', 'inline');
        }
        abort_unless(in_array($evidence->kind, self::INLINE_KINDS, true), 404);
        $this->afterSuccess($evidence, $user, EvidenceAccessAction::Stream);

        return $this->serve($evidence->storage_path, $evidence->mime_type, EvidenceResource::displayName($evidence), 'inline');
    }

    public function log(Evidence $evidence, ?User $user, EvidenceAccessAction $action, bool $success, ?string $reason = null): void
    {
        EvidenceAccessLog::query()->create([
            'evidence_id' => $evidence->id,
            'user_id' => $user?->id,
            'action' => $action->value,
            'ip_address' => Request::ip(),
            'user_agent' => mb_substr((string) Request::userAgent(), 0, 500),
            'success' => $success,
            'reason' => $reason,
            'created_at' => now(),
        ]);
    }

    private function afterSuccess(Evidence $evidence, ?User $user, EvidenceAccessAction $action): void
    {
        // Non-public evidence accessed by someone other than its owner is a high-risk event.
        $isOwner = $user !== null && $user->investigator?->id === $evidence->investigator_id;
        if ($evidence->visibility !== EvidenceVisibility::Public && ! $isOwner) {
            $this->audit->log('evidence.accessed', $evidence, null, ['action' => $action->value, 'visibility' => $evidence->visibility->value], $user);
        }
        $this->analytics->record(
            $action === EvidenceAccessAction::Download ? 'evidence_downloaded' : 'evidence_previewed',
            ['user_id' => $user?->id, 'investigation_id' => $evidence->investigation_id, 'investigator_id' => $evidence->investigator_id],
        );
    }

    private function serve(string $path, string $mime, string $filename, string $disposition): Response
    {
        abort_unless($this->vault->exists($path), 404);
        $fallback = preg_replace('/[^A-Za-z0-9._-]/', '_', $filename) ?: 'file';
        $headers = [
            'Content-Type' => $mime,
            'Content-Disposition' => HeaderUtils::makeDisposition($disposition, $filename, $fallback),
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ];

        $config = config('filesystems.disks.'.$this->vault->diskName());
        if (($config['driver'] ?? null) === 'local') {
            $local = $this->vault->withLocalPath($path, fn (string $p) => $p);
            $response = new BinaryFileResponse($local, 200, $headers, false, null, false, false);
            $response->headers->set('Content-Type', $mime);
            $response->headers->set('Content-Disposition', $headers['Content-Disposition']);

            return $response;
        }

        return new StreamedResponse(function () use ($path) {
            $stream = $this->vault->readStream($path);
            fpassthru($stream);
            fclose($stream);
        }, 200, $headers);
    }
}
