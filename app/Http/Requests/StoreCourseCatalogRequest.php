<?php

namespace App\Http\Requests;

use App\Models\Course;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCourseCatalogRequest extends FormRequest
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
            'courses' => ['required', 'array', 'min:1', 'max:120'],
            'courses.*.code' => ['required', 'string', 'max:32', 'regex:/^(?:[A-Z]{2,10}-)?[A-Z]{2,10} [0-9]{2,4}[A-Z]?$/'],
            'courses.*.title' => ['required', 'string', 'max:255'],
            'courses.*.credit_units' => ['required', 'integer', 'between:1,10'],
            'courses.*.is_elective' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $courses = $this->input('courses');

        if (! is_array($courses)) {
            return;
        }

        $this->merge(['courses' => collect($courses)->map(fn ($course): mixed => is_array($course) ? [
            ...$course,
            'code' => Course::normalizeCode((string) ($course['code'] ?? '')),
        ] : $course)->all()]);
    }
}
