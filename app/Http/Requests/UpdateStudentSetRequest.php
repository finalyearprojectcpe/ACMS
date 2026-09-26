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
            'current_academic_year_id' => ['sometimes', 'nullable', 'integer', 'exists:academic_years,id'],
        ];
    }

    public function attributes(): array
    {
        return ['current_academic_year_id' => 'academic year'];
    }
}
