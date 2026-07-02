<?php

namespace App\Http\Requests;

use App\AccountType;
use App\Models\Faculty;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

class SchoolFacultyRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'abbreviation' => ['required', 'string', 'max:10'],
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

                $ignoreFaculty = $this->route('faculty');
                $name = (string) $this->input('name');
                $abbreviation = (string) $this->input('abbreviation');
                $slug = Str::slug($name);

                $duplicateName = Faculty::query()
                    ->where('school_id', $user->school_id)
                    ->where('slug', $slug)
                    ->when(
                        $ignoreFaculty instanceof Faculty,
                        fn ($query) => $query->whereKeyNot($ignoreFaculty->id),
                    )
                    ->exists();

                if ($duplicateName) {
                    $validator->errors()->add('name', 'This school already has a faculty with this name.');
                }

                $duplicateAbbreviation = Faculty::query()
                    ->where('school_id', $user->school_id)
                    ->where('abbreviation', $abbreviation)
                    ->when(
                        $ignoreFaculty instanceof Faculty,
                        fn ($query) => $query->whereKeyNot($ignoreFaculty->id),
                    )
                    ->exists();

                if ($duplicateAbbreviation) {
                    $validator->errors()->add('abbreviation', 'This abbreviation is already used by another faculty in this school.');
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
