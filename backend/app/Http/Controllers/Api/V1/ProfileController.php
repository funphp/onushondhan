<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserStatus;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Resources\MeResource;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\PhoneVerificationService;
use App\Services\SessionRevoker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function update(Request $request): MeResource
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'username' => ['sometimes', 'string', 'min:3', 'max:40', 'regex:/^[A-Za-z0-9_\-]+$/', Rule::unique('users', 'username')->ignore($user->id)],
            'email' => ['sometimes', 'email:rfc', 'max:190', Rule::unique('users', 'email')->ignore($user->id)],
            'notification_preferences' => ['sometimes', 'array'],
            'notification_preferences.email' => ['sometimes', 'array'],
            'notification_preferences.email.*' => ['boolean'],
            'locale' => ['sometimes', Rule::in(config('onushondhan.supported_locales'))],
        ]);

        $before = $user->only(['name', 'username', 'email']);
        $emailChanged = isset($data['email']) && mb_strtolower($data['email']) !== $user->email;
        if (isset($data['email'])) {
            $data['email'] = mb_strtolower($data['email']);
        }

        DB::transaction(function () use ($user, $data, $emailChanged, $before): void {
            $user->fill($data);
            if ($emailChanged) {
                $user->email_verified_at = null;
            }
            $user->save();
            $this->audit->log('user.profile_updated', $user, $before, $user->only(['name', 'username', 'email']), $user);
        });
        if ($emailChanged) {
            $user->sendEmailVerificationNotification();
        }

        return new MeResource($user->fresh());
    }

    public function startPhoneVerification(Request $request, PhoneVerificationService $service): JsonResponse
    {
        $data = $request->validate(['phone' => ['required', 'string', 'max:32']]);
        $service->start($request->user(), $data['phone']);

        return response()->json(['message' => __('A verification code has been sent.')]);
    }

    public function confirmPhone(Request $request, PhoneVerificationService $service): MeResource
    {
        $data = $request->validate(['code' => ['required', 'string', 'size:6']]);
        $service->verify($request->user(), $data['code']);

        return new MeResource($request->user()->fresh());
    }

    public function deactivate(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validate(['password' => ['required', 'string']]);
        if (! Hash::check($data['password'], $user->password)) {
            throw new ApiException('The password is incorrect.', 'invalid_password', 422, ['password' => ['The password is incorrect.']]);
        }
        if ($user->isSuperAdmin()) {
            throw ApiException::forbidden('Super admin accounts cannot be self-deactivated.');
        }

        DB::transaction(function () use ($user): void {
            $user->forceFill(['status' => UserStatus::Deactivated, 'status_reason' => 'self_deactivated'])->save();
            app(SessionRevoker::class)->revokeUser($user);
            $this->audit->log('user.deactivated', $user, null, ['by' => 'self'], $user);
        });
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        Auth::forgetGuards();

        return response()->json(['message' => __('Your account has been deactivated.')]);
    }
}
