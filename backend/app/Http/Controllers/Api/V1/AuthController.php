<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\MeResource;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\AuthService;
use App\Services\SecurityLogger;
use App\Services\Settings;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly AuditLogger $audit,
        private readonly SecurityLogger $security,
    ) {}

    public function register(RegisterRequest $request, Settings $settings): JsonResponse
    {
        if (! $settings->get('registration.open')) {
            throw new ApiException('Registration is currently closed.', 'registration_closed', 403);
        }
        $user = $this->auth->register($request->validated());
        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return (new MeResource($user->fresh()))->response()->setStatusCode(201);
    }

    public function login(LoginRequest $request): MeResource
    {
        $user = $this->auth->attemptLogin($request->string('email')->value(), $request->string('password')->value(), $request->boolean('remember'));
        // Prevent session fixation.
        $request->session()->regenerate();

        return new MeResource($user);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request): MeResource
    {
        return new MeResource($request->user());
    }

    public function resendVerification(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if (! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        return response()->json(['message' => 'If your email is unverified, a new verification link has been sent.']);
    }

    public function verifyEmail(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id' => ['required', 'string', 'max:40'],
            'hash' => ['required', 'string', 'size:40'],
            'expires' => ['required', 'integer'],
            'signature' => ['required', 'string', 'size:64'],
        ]);
        $this->auth->verifyEmail($data['id'], $data['hash'], (int) $data['expires'], $data['signature']);

        return response()->json(['message' => 'Email verified.', 'verified' => true]);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:190']]);
        $status = Password::sendResetLink(['email' => mb_strtolower($data['email'])]);
        if ($status === Password::RESET_LINK_SENT) {
            $this->security->event('password.reset_requested', User::query()->where('email', mb_strtolower($data['email']))->first());
        }

        // Identical response whether or not the account exists (no user enumeration).
        return response()->json(['message' => 'If an account exists for that address, a reset link has been sent.']);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:190'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::reset(
            ['email' => mb_strtolower($data['email']), 'password' => $data['password'], 'password_confirmation' => $request->input('password_confirmation'), 'token' => $data['token']],
            function (User $user, string $password): void {
                $user->forceFill(['password' => $password])->save();
                $this->auth->logoutOtherSessions($user, null);
                $this->audit->log('user.password_reset', $user, null, null, $user);
                $this->security->event('password.reset', $user, 'warning');
                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw new ApiException('This password reset link is invalid or has expired.', 'reset_invalid', 422);
        }

        return response()->json(['message' => 'Your password has been reset. Please sign in.']);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);
        /** @var User $user */
        $user = $request->user();
        if (! Hash::check($data['current_password'], $user->password)) {
            throw new ApiException('The current password is incorrect.', 'invalid_current_password', 422, ['current_password' => ['The current password is incorrect.']]);
        }
        $user->forceFill(['password' => $data['password']])->save();
        $request->session()->regenerate();
        $this->auth->logoutOtherSessions($user, $request->session()->getId());
        $this->audit->log('user.password_changed', $user, null, null, $user);
        $this->security->event('password.changed', $user, 'warning');

        return response()->json(['message' => 'Password updated. Other sessions were signed out.']);
    }
}
