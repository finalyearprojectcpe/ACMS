<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class RegisterStudentRequest extends RegisterAccountRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->passwordRules(),
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class, 'email')],
        ];
    }
}
