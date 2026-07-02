<?php

namespace App\Http\Requests\Auth;

use App\Models\StudentProfile;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class VerifyStudentRegistrationRequest extends RegisterAccountRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $school = $this->currentSchool();
        $registrationNumberRule = Rule::exists(StudentProfile::class, 'registration_number');

        if ($school !== null) {
            $registrationNumberRule->where('school_id', $school->id);
        }

        return [
            ...$this->accessCodeRules(),
            'registration_number' => [
                'required',
                'string',
                'regex:/^[0-9]+$/',
                'max:255',
                $registrationNumberRule,
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $this->merge([
            'registration_number' => trim((string) $this->input('registration_number', '')),
        ]);
    }
}
