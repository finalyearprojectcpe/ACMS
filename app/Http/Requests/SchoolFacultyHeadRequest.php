<?php

namespace App\Http\Requests;

use App\AccountType;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

class SchoolFacultyHeadRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->account_type === AccountType::SchoolAdmin;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'faculty_id' => ['required', 'integer', 'exists:faculties,id'],
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'access_code' => ['nullable', 'string', 'max:64'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $user = $this->user();

                if (! $user instanceof User || $validator->errors()->isNotEmpty()) {
                    return;
                }

                $faculty = Faculty::query()
                    ->where('school_id', $user->school_id)
                    ->whereKey((int) $this->input('faculty_id'))
                    ->first();

                if (! $faculty instanceof Faculty) {
                    $validator->errors()->add('faculty_id', 'Choose a faculty in this school.');

                    return;
                }

                $department = Department::query()
                    ->where('faculty_id', $faculty->id)
                    ->whereKey((int) $this->input('department_id'))
                    ->first();

                if (! $department instanceof Department) {
                    $validator->errors()->add('department_id', 'Choose a department under the selected faculty.');
                }

                $existingUser = User::query()
                    ->with(['staffProfile.headedDepartment', 'staffProfile.headedFaculty'])
                    ->where('school_id', $user->school_id)
                    ->where('email', (string) $this->input('email'))
                    ->first();

                if ($existingUser === null) {
                    return;
                }

                if ($existingUser->account_type !== AccountType::Staff) {
                    $validator->errors()->add('email', 'This email is already used by a non-staff account in this school.');

                    return;
                }

                $headedFaculty = $existingUser->staffProfile?->headedFaculty;
                $headedDepartment = $existingUser->staffProfile?->headedDepartment;

                if ($headedFaculty !== null && (int) $headedFaculty->id !== (int) $faculty->id) {
                    $validator->errors()->add('email', "This staff member is already Faculty Head for {$headedFaculty->abbreviation}.");
                }

                if ($headedDepartment !== null) {
                    $validator->errors()->add('email', "This staff member is already HOD of {$headedDepartment->abbreviation}.");
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => Str::of((string) $this->input('name'))->squish()->title()->toString(),
            'email' => Str::lower(trim((string) $this->input('email'))),
            'access_code' => trim((string) $this->input('access_code', '')),
        ]);
    }
}
