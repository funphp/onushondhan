<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use App\Services\AgreementService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** The authenticated user's own account view. Only ever returned to that user. */
class MeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;
        $investigator = $user->investigator;
        $pending = app(AgreementService::class)->pendingFor($user);

        return [
            'id' => $user->public_id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'email_verified' => $user->hasVerifiedEmail(),
            'phone' => $user->phone,
            'phone_verified' => $user->phone_verified_at !== null,
            'status' => $user->status->value,
            'roles' => $user->roleSlugs(),
            'permissions' => $user->isSuperAdmin() ? ['*'] : $user->permissionSlugs(),
            'is_staff' => $user->isStaff(),
            'investigator' => $investigator === null ? null : [
                'id' => $investigator->public_id,
                'public_name' => $investigator->public_name,
                'slug' => $investigator->slug,
                'identity_mode' => $investigator->identity_mode->value,
                'status' => $investigator->status->value,
            ],
            'pending_agreements' => array_map(fn ($t) => [
                'id' => $t->id,
                'agreement_type' => $t->agreement_type->value,
                'version' => $t->version,
                'title' => $t->title,
            ], $pending),
            'notification_preferences' => $user->notification_preferences ?? (object) [],
            'locale' => $user->preferredLocale(),
            'unread_notifications' => $user->unreadNotifications()->count(),
            'created_at' => $user->created_at->toIso8601String(),
        ];
    }
}
