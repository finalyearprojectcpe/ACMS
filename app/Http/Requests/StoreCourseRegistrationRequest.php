<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCourseRegistrationRequest extends FormRequest
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
            'action' => ['required', Rule::in(['draft', 'assign'])],
            'from' => ['sometimes', Rule::in(['home', 'profile'])],
            'items' => ['present', 'array', $this->input('action') === 'assign' ? 'min:1' : 'min:0', 'max:30'],
            'items.*.course_offering_id' => ['required', 'integer', 'distinct', 'exists:course_offerings,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.min' => 'Select at least one course before assigning courses to this student.',
            'items.*.course_offering_id.distinct' => 'A course appears more than once. Remove the duplicate course and try again.',
        ];
    }
}
