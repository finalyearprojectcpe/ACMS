<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreStudentSetRequest extends FormRequest
{
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
        $advisorProfileId = $this->user()?->advisorProfile?->id ?? 0;

        return [
            'department_name' => ['required', 'string', 'max:120'],
            'department_slug' => ['required', 'string', 'max:140'],
            'start_year' => [
                'required',
                'integer',
                'between:1900,2200',
                Rule::unique('student_sets', 'entry_start_year')->where(
                    fn ($query) => $query
                        ->where('advisor_profile_id', $advisorProfileId)
                        ->where('department_slug', $this->string('department_slug')->toString())
                        ->where('entry_end_year', $this->integer('end_year')),
                ),
            ],
            'end_year' => ['required', 'integer', 'between:1901,2201'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $departmentName = Str::of((string) $this->input('department_name'))
            ->squish()
            ->lower()
            ->title()
            ->toString();
        $startYear = filter_var($this->input('start_year'), FILTER_VALIDATE_INT);

        $this->merge([
            'department_name' => $departmentName,
            'department_slug' => Str::slug($departmentName),
            'end_year' => $startYear === false ? null : $startYear + 1,
        ]);
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'start_year.unique' => 'You already have this student set.',
        ];
    }
}
