<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HodStudentClasslistExportRequest extends FormRequest
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
            'query' => ['nullable', 'string', 'max:120'],
            'level' => ['nullable', 'string', 'max:40'],
            'set' => ['nullable', 'string', 'max:20'],
            'carryover' => ['nullable', Rule::in(['Has carryover', 'No carryover'])],
        ];
    }
}
