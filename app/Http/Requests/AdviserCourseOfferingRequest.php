<?php

namespace App\Http\Requests;

use App\Models\Course;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class AdviserCourseOfferingRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:32', 'regex:/^(?:[A-Z]{2,10}-)?[A-Z]{2,10} [0-9]{2,4}[A-Z]?$/'],
            'title' => ['required', 'string', 'max:255'],
            'credit_units' => ['required', 'integer', 'min:1', 'max:10'],
            'is_elective' => ['required', 'boolean'],
            'prerequisite_course_ids' => ['array'],
            'prerequisite_course_ids.*' => ['integer', 'exists:courses,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => Course::normalizeCode((string) $this->input('code')),
            'title' => Str::of((string) $this->input('title'))->squish()->toString(),
            'is_elective' => filter_var($this->input('is_elective'), FILTER_VALIDATE_BOOL),
        ]);
    }
}
