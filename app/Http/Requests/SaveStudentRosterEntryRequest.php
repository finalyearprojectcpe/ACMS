<?php

namespace App\Http\Requests;

use App\Models\StudentProfile;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SaveStudentRosterEntryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdviser() === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'official_name' => ['required', 'string', 'max:255'],
            'registration_number' => [
                'required', 'string', 'max:255',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($this->user()?->advisorProfile?->students()
                        ->where('registration_number_key', StudentProfile::registrationKey((string) $value))
                        ->when($this->existingStudent(), fn ($query, StudentProfile $student) => $query->where('id', '!=', $student->id))
                        ->exists()) {
                        $fail('That registration number is already on your class list.');
                    }
                },
            ],
            'email' => ['required_if:account_active,1', 'nullable', 'email', 'max:255', Rule::unique(User::class, 'email')->ignore($this->existingStudent()?->user_id)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'official_name' => Str::squish((string) $this->input('official_name')),
            'registration_number' => Str::squish((string) $this->input('registration_number')),
            'email' => $this->has('email') ? Str::lower(Str::squish((string) $this->input('email'))) : null,
            'account_active' => $this->existingStudent()?->user_id !== null ? '1' : '0',
        ]);
    }

    private function existingStudent(): ?StudentProfile
    {
        $registrationNumber = $this->route('registrationNumber');

        if (! is_string($registrationNumber)) {
            return null;
        }

        return $this->user()?->advisorProfile?->students()
            ->where('registration_number_key', StudentProfile::registrationKey(StudentProfile::registrationNumberFromRoute($registrationNumber)))
            ->first();
    }
}
