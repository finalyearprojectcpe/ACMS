<?php

namespace App\Http\Requests;

use App\CourseCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdviserCourseOfferingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20'],
            'title' => ['required', 'string', 'max:255'],
            'credit_units' => ['required', 'integer', 'min:1', 'max:10'],
            'is_elective' => ['required', 'boolean'],
            'category' => ['required', Rule::enum(CourseCategory::class)],
            'prerequisite_course_ids' => ['array'],
            'prerequisite_course_ids.*' => ['integer', 'exists:courses,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => Str::of((string) $this->input('code'))->squish()->upper()->toString(),
            'title' => Str::of((string) $this->input('title'))->squish()->toString(),
            'category' => filter_var($this->input('is_elective'), FILTER_VALIDATE_BOOL)
                ? CourseCategory::Elective->value
                : CourseCategory::Normal->value,
        ]);
    }
}
