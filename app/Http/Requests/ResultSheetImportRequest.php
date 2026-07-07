<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResultSheetImportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'entry_academic_session_id' => ['required', 'integer', 'exists:academic_sessions,id'],
            'academic_year_semester_id' => ['required', 'integer', 'exists:academic_year_semesters,id'],
            'action' => ['required', Rule::in(['draft', 'submit'])],
            'courses' => ['required', 'array', 'min:1', 'max:40'],
            'courses.*.course_offering_id' => ['required', 'integer', 'exists:course_offerings,id'],
            'courses.*.code' => ['required', 'string', 'max:30'],
            'courses.*.title' => ['required', 'string', 'max:180'],
            'courses.*.credit_units' => ['required', 'integer', 'min:1', 'max:12'],
            'rows' => ['required', 'array', 'min:1', 'max:400'],
            'rows.*.row_number' => ['nullable', 'integer', 'min:1'],
            'rows.*.registration_number' => ['required', 'string', 'max:40'],
            'rows.*.grades' => ['required', 'array'],
            'rows.*.grades.*' => ['nullable', 'string', Rule::in(['A', 'B', 'C', 'D', 'E', 'F', 'a', 'b', 'c', 'd', 'e', 'f'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'courses.required' => 'Add the course columns from the spreadsheet before saving the results.',
            'courses.*.course_offering_id.required' => 'Every course column must be matched to a course offering before saving the results.',
            'courses.*.course_offering_id.exists' => 'One of the selected course offerings could not be found. Refresh the page and try again.',
            'courses.*.code.required' => 'Every course column needs a course code.',
            'courses.*.title.required' => 'Every course column needs the full course title.',
            'courses.*.credit_units.required' => 'Every course column needs its unit value.',
            'courses.*.credit_units.max' => 'Course units should not be more than 12.',
            'rows.required' => 'Add the student rows from the spreadsheet before saving the results.',
            'rows.*.registration_number.required' => 'Every result row needs a student registration number.',
            'rows.*.grades.required' => 'Every result row needs grade columns.',
            'rows.*.grades.*.in' => 'Grades must be A, B, C, D, E, or F.',
        ];
    }
}
