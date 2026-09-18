<?php

namespace App\Http\Requests;

use App\Models\StudentProfile;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreAdvisedStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdviser() === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $advisorProfileId = $this->user()?->advisorProfile?->id ?? 0;

        return [
            'student_set_id' => [
                'required',
                'integer',
                Rule::exists('student_sets', 'id')->where('advisor_profile_id', $advisorProfileId),
            ],
            'registration_number' => [
                'required',
                'string',
                'regex:/^(19|20)\d{6,18}$/',
                Rule::unique(StudentProfile::class, 'registration_number')->where('advisor_profile_id', $advisorProfileId),
            ],
            'access_code' => ['nullable', 'string', 'min:6', 'max:64'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'registration_number' => Str::of((string) $this->input('registration_number'))->squish()->toString(),
            'access_code' => Str::upper(trim((string) $this->input('access_code', ''))),
        ]);
    }

    public function messages(): array
    {
        return [
            'registration_number.regex' => 'Enter a numeric registration number beginning with the student entry year, for example 2021001234.',
            'registration_number.unique' => 'A student with this registration number already exists in your workspace.',
        ];
    }
}
