<?php

namespace App\Http\Requests\Auth;

use App\Models\StudentProfile;
use App\Models\StudentSet;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterInvitedStudentRequest extends FormRequest
{
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
        $studentSet = $this->route('studentSet');
        $advisorProfileId = $studentSet instanceof StudentSet
            ? $studentSet->advisor_profile_id
            : 0;

        return [
            'surname' => ['required', 'string', 'max:80'],
            'other_names' => ['required', 'string', 'max:160'],
            'registration_number' => [
                'required',
                'string',
                'regex:/^(19|20)\d{6,18}$/',
                Rule::unique(StudentProfile::class, 'registration_number')
                    ->where('advisor_profile_id', $advisorProfileId),
            ],
            'email' => ['required', 'email', 'max:255', Rule::unique(User::class, 'email')],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'surname' => Str::squish((string) $this->input('surname')),
            'other_names' => Str::squish((string) $this->input('other_names')),
            'registration_number' => Str::squish((string) $this->input('registration_number')),
            'email' => Str::lower(trim((string) $this->input('email'))),
        ]);
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'registration_number.regex' => 'Enter a numeric registration number beginning with your entry year, for example 2024001234.',
            'registration_number.unique' => 'This registration number has already joined your course adviser.',
            'email.unique' => 'An account already uses this email. Log in instead or use another email.',
        ];
    }
}
