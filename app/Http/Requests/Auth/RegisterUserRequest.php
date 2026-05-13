<?php

namespace App\Http\Requests\Auth;

use App\AccountType;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RegisterUserRequest extends FormRequest
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
        $accountType = $this->string('account_type')->toString();

        return [
            'account_type' => ['required', Rule::enum(AccountType::class)],
            'surname' => ['required', 'string', 'max:255'],
            'other_names' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class, 'email')],
            'password' => ['required', 'string', 'min:8'],
            'faculty_id' => ['required', 'integer', Rule::exists(Faculty::class, 'id')],
            'department_id' => [
                'required',
                'integer',
                Rule::exists(Department::class, 'id')
                    ->where(fn (Builder $query) => $query->where('faculty_id', $this->integer('faculty_id'))),
            ],
            'registration_number' => [
                Rule::requiredIf($accountType === AccountType::Student->value),
                Rule::prohibitedIf($accountType === AccountType::Staff->value),
                'nullable',
                'string',
                'regex:/^[0-9]+$/',
                'max:255',
                Rule::unique(StudentProfile::class, 'registration_number'),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $registrationNumber = trim((string) $this->input('registration_number', ''));

        $this->merge([
            'account_type' => Str::lower(trim((string) $this->input('account_type', ''))),
            'surname' => trim((string) $this->input('surname', '')),
            'other_names' => trim((string) $this->input('other_names', '')),
            'email' => Str::lower(trim((string) $this->input('email', ''))),
            'registration_number' => $registrationNumber === '' ? null : $registrationNumber,
        ]);
    }
}
