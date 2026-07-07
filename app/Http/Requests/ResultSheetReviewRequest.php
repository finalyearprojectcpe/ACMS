<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResultSheetReviewRequest extends FormRequest
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
            'action' => ['required', Rule::in(['approve', 'return'])],
            'correction_reason' => [
                'nullable',
                'string',
                Rule::in(['incomplete_data', 'incorrect_grades', 'missing_students_or_courses']),
            ],
            'correction_note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
