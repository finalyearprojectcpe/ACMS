<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterAdvisorRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique(User::class, 'email')],
            'institution_name' => ['nullable', 'string', 'max:255'],
            'programme_name' => ['required', 'string', 'max:255'],
            'programme_abbreviation' => ['required', 'string', 'max:20'],
            'minimum_years_to_degree' => ['required', 'integer', 'between:1,7'],
            'maximum_course_units' => ['required', 'integer', 'between:1,60'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'email' => Str::lower(trim((string) $this->input('email'))),
            'institution_name' => trim((string) $this->input('institution_name')) ?: null,
            'programme_name' => trim((string) $this->input('programme_name')),
            'programme_abbreviation' => Str::upper(trim((string) $this->input('programme_abbreviation'))),
        ]);
    }
}
