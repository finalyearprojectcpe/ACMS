<?php

namespace App\Http\Requests\Auth;

use App\AccountStatus;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $credentials = $this->only('email', 'password');
        if (Auth::attempt($credentials)) {
            $this->ensureUserCanLogin();

            return;
        }

        $this->throwFailedAuthenticationException();
    }

    private function ensureUserCanLogin(): void
    {
        $user = $this->user();

        if (! ($user instanceof User)) {
            return;
        }

        if (
            $user->activated_at === null
            || $user->disabled_at !== null
            || $user->status !== AccountStatus::Active
        ) {
            Auth::guard('web')->logout();

            $this->throwFailedAuthenticationException();
        }

    }

    private function throwFailedAuthenticationException(): never
    {
        throw ValidationException::withMessages([
            'email' => trans('auth.failed'),
        ]);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => Str::lower(trim((string) $this->input('email', ''))),
        ]);
    }
}
