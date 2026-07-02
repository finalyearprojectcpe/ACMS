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

class SchoolDepartmentRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'abbreviation' => ['required', 'string', 'max:10'],
            'minimum_years_to_degree' => ['required', 'integer', 'min:1', 'max:7'],
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

                $ignoreDepartment = $this->route('department');
                $name = (string) $this->input('name');
                $abbreviation = (string) $this->input('abbreviation');
                $slug = Str::slug($name);

                $duplicateName = Department::query()
                    ->where('faculty_id', $faculty->id)
                    ->where('slug', $slug)
                    ->when(
                        $ignoreDepartment instanceof Department,
                        fn ($query) => $query->whereKeyNot($ignoreDepartment->id),
                    )
                    ->exists();

                if ($duplicateName) {
                    $validator->errors()->add('name', 'This faculty already has a department with this name.');
                }

                $duplicateAbbreviation = Department::query()
                    ->where('faculty_id', $faculty->id)
                    ->where('abbreviation', $abbreviation)
                    ->when(
                        $ignoreDepartment instanceof Department,
                        fn ($query) => $query->whereKeyNot($ignoreDepartment->id),
                    )
                    ->exists();

                if ($duplicateAbbreviation) {
                    $validator->errors()->add('abbreviation', 'This abbreviation is already used by another department in this faculty.');
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => Str::of((string) $this->input('name'))->squish()->title()->toString(),
            'abbreviation' => Str::of((string) $this->input('abbreviation'))->squish()->upper()->toString(),
        ]);
    }
}
