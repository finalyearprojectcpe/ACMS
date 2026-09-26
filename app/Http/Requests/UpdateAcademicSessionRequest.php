<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAcademicSessionRequest extends FormRequest
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
        return [
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'start_year' => ['required', 'integer', 'between:1900,2200'],
            'end_year' => ['required', 'integer', 'between:1901,2201'],
            'first_semester_maximum_course_units' => ['required', 'integer', 'between:1,60'],
            'second_semester_maximum_course_units' => ['required', 'integer', 'between:1,60'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $startYear = filter_var($this->input('start_year'), FILTER_VALIDATE_INT);

        $this->merge([
            'start_year' => $startYear === false ? null : $startYear,
            'end_year' => $startYear === false ? null : $startYear + 1,
        ]);
    }
}
