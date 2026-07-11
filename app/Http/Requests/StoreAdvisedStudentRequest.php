<?php

namespace App\Http\Requests;

use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreAdvisedStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->staffProfile !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->user();

        return [
            'entry_academic_session_id' => [
                'required',
                'integer',
                Rule::exists('academic_sessions', 'id')
                    ->where('school_id', $user instanceof User ? $user->school_id : 0),
            ],
            'registration_number' => [
                'required',
                'string',
                'regex:/^(19|20)\d{6,18}$/',
                Rule::unique(StudentProfile::class, 'registration_number')
                    ->where('school_id', $user instanceof User ? $user->school_id : 0),
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
            'registration_number.unique' => 'A student with this registration number already exists in this school.',
        ];
    }
}
