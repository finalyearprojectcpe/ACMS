<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class ImportFacultyStaffRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user instanceof User) {
            return false;
        }

        $user->loadMissing('staffProfile.headedFaculty');

        return $user->staffProfile?->headedFaculty !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file_name' => ['nullable', 'string', 'max:255'],
            'import_key' => ['nullable', 'string', 'max:80'],
            'batch_number' => ['nullable', 'integer', 'min:1'],
            'total_batches' => ['nullable', 'integer', 'min:1'],
            'start_row' => ['nullable', 'integer', 'min:1'],
            'total_rows' => ['nullable', 'integer', 'min:1'],
            'batch_size' => ['nullable', 'integer', 'min:1', 'max:200'],
            'mappings' => ['nullable', 'array'],
            'mappings.*' => ['nullable', 'string', 'max:255'],
            'rows' => ['required', 'array', 'min:1', 'max:200'],
            'rows.*.department' => ['required', 'string', 'max:255'],
            'rows.*.name' => ['required', 'string', 'max:255'],
            'rows.*.email' => ['required', 'string', 'email', 'max:255'],
            'rows.*.accessCode' => ['nullable', 'string', 'max:64'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $rows = collect($this->input('rows', []))
            ->map(fn (mixed $row): array => is_array($row) ? [
                'department' => trim((string) ($row['department'] ?? '')),
                'name' => trim((string) ($row['name'] ?? '')),
                'email' => Str::lower(trim((string) ($row['email'] ?? ''))),
                'accessCode' => trim((string) ($row['accessCode'] ?? '')),
            ] : [])
            ->filter(fn (array $row): bool => collect($row)->filter()->isNotEmpty())
            ->values()
            ->all();

        $this->merge([
            'file_name' => trim((string) $this->input('file_name', '')),
            'import_key' => trim((string) $this->input('import_key', '')),
            'batch_number' => $this->integer('batch_number') ?: 1,
            'total_batches' => $this->integer('total_batches') ?: 1,
            'start_row' => $this->integer('start_row') ?: 1,
            'total_rows' => $this->integer('total_rows') ?: count($rows),
            'batch_size' => $this->integer('batch_size') ?: 200,
            'rows' => $rows,
        ]);
    }
}
