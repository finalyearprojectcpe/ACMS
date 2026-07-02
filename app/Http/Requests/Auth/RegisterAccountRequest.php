<?php

namespace App\Http\Requests\Auth;

use App\Models\School;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

abstract class RegisterAccountRequest extends FormRequest
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
        return $this->passwordRules();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function passwordRules(): array
    {
        return [
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function accessCodeRules(): array
    {
        return [
            'access_code' => ['required', 'string', 'max:255'],
        ];
    }

    protected function currentSchool(): ?School
    {
        $school = $this->attributes->get('school');

        return $school instanceof School ? $school : null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'access_code' => trim((string) $this->input('access_code', '')),
            'email' => Str::lower(trim((string) $this->input('email', ''))),
        ]);
    }
}
