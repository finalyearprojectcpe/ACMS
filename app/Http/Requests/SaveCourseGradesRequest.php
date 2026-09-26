<?php

namespace App\Http\Requests;

use App\Grade;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCourseGradesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdviser() === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'grades' => ['required', 'array'],
            'grades.*' => ['nullable', Rule::enum(Grade::class)],
        ];
    }
}
