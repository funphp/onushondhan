<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'username' => ['nullable', 'string', 'min:3', 'max:40', 'regex:/^[A-Za-z0-9_\-]+$/', 'unique:users,username'],
            'email' => ['required', 'string', 'email:rfc', 'max:190', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            // Explicit consent to the current Terms, Privacy Policy and Content Policy.
            'accept_agreements' => ['required', 'accepted'],
            // Interface language chosen in the switcher; used for emails such as verification.
            'locale' => ['sometimes', Rule::in(config('onushondhan.supported_locales'))],
        ];
    }
}
