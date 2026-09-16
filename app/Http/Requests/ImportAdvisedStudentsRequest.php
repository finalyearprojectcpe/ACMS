<?php

namespace App\Http\Requests;

use App\Models\StudentProfile;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ImportAdvisedStudentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->account_type === \App\AccountType::Adviser
            && $this->user()?->advisorProfile !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'student_set_id' => [
                'required',
                'integer',
                Rule::exists('student_sets', 'id')
                    ->where('advisor_profile_id', $this->user()?->advisorProfile?->id ?? 0),
            ],
            'file_name' => ['nullable', 'string', 'max:255'],
            'import_key' => ['nullable', 'string', 'max:80'],
            'batch_number' => ['nullable', 'integer', 'min:1'],
            'total_batches' => ['nullable', 'integer', 'min:1'],
            'start_row' => ['nullable', 'integer', 'min:1'],
            'total_rows' => ['nullable', 'integer', 'min:1'],
            'batch_size' => ['nullable', 'integer', 'min:1', 'max:200'],
            'mappings' => ['nullable', 'array'],
            'rows' => ['required', 'array', 'min:1', 'max:200'],
            'rows.*.registrationNumber' => ['required', 'string', 'regex:/^(19|20)\d{6,18}$/'],
            'rows.*.accessCode' => ['nullable', 'string', 'max:64'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $rows = collect($this->input('rows', []))
            ->map(fn (mixed $row): array => is_array($row) ? [
                'registrationNumber' => Str::of((string) ($row['registrationNumber'] ?? ''))->squish()->toString(),
                'accessCode' => Str::upper(trim((string) ($row['accessCode'] ?? ''))),
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

    public function messages(): array
    {
        return [
            'rows.*.registrationNumber.regex' => 'Each registration number must contain only numbers and begin with the student entry year, for example 2021001234.',
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $rows = collect($this->input('rows', []));
            $advisorProfileId = $this->user()?->advisorProfile?->id;
            $existingRegistrationNumbers = $advisorProfileId === null
                ? collect()
                : StudentProfile::query()
                    ->where('advisor_profile_id', $advisorProfileId)
                    ->whereIn('registration_number', $rows->pluck('registrationNumber'))
                    ->pluck('registration_number')
                    ->flip();
            $seenRegistrationNumbers = [];

            foreach ($rows as $index => $row) {
                $registrationNumber = (string) ($row['registrationNumber'] ?? '');

                if ($registrationNumber === '') {
                    continue;
                }

                if ($existingRegistrationNumbers->has($registrationNumber)) {
                    $validator->errors()->add(
                        "rows.{$index}.registrationNumber",
                        'A student with this registration number already exists in your workspace.',
                    );
                }

                if (isset($seenRegistrationNumbers[$registrationNumber])) {
                    $firstRow = $seenRegistrationNumbers[$registrationNumber] + 1;
                    $validator->errors()->add(
                        "rows.{$index}.registrationNumber",
                        "This registration number is already listed on row {$firstRow}.",
                    );
                }

                $seenRegistrationNumbers[$registrationNumber] = $index;
            }
        }];
    }
}
