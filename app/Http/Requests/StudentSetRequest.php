<?php

namespace App\Http\Requests;

use App\AccountType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StudentSetRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->account_type === AccountType::Adviser;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'entry_academic_session_id' => [
                $this->route('studentSet') ? 'sometimes' : 'required',
                'integer',
                'exists:academic_sessions,id',
            ],
            'minimum_years_to_degree' => ['required', 'integer', 'min:1', 'max:10'],
            'maximum_course_units' => ['required', 'integer', 'min:1', 'max:40'],
        ];
    }
}
