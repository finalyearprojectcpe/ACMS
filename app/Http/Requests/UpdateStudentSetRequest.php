<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateStudentSetRequest extends FormRequest
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
            'student_signup_enabled' => ['sometimes', 'required', 'boolean'],
            'current_academic_year_id' => ['sometimes', 'required', 'integer', 'exists:academic_years,id'],
            'current_semester_id' => ['required_with:current_academic_year_id', 'integer', 'exists:semesters,id'],
        ];
    }

    public function attributes(): array
    {
        return [
            'current_academic_year_id' => 'academic year',
            'current_semester_id' => 'semester',
        ];
    }
}
