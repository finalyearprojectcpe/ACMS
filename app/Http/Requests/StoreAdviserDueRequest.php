<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAdviserDueRequest extends FormRequest
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
            'student_set_id' => ['required', 'integer', 'exists:student_sets,id'],
            'title' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:160'],
            'amount_naira' => ['required', 'integer', 'min:100', 'max:1000000'],
            'due_on' => ['nullable', 'date'],
            'action' => ['required', Rule::in(['draft', 'publish'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'student_set_id.required' => 'Select the student set that should receive this due.',
            'amount_naira.required' => 'Enter the amount students should pay.',
            'amount_naira.min' => 'The due amount should be at least NGN 100.',
            'amount_naira.max' => 'The due amount looks too high. Check it and try again.',
        ];
    }
}
