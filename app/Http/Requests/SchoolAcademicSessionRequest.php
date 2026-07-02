<?php

namespace App\Http\Requests;

use App\AccountType;
use App\Models\AcademicSession;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

class SchoolAcademicSessionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->account_type === AccountType::SchoolAdmin;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:20'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after:starts_on'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $user = $this->user();

                if (! $user instanceof User || $validator->errors()->isNotEmpty()) {
                    return;
                }

                $ignoreAcademicSession = $this->route('academicSession');
                $name = (string) $this->input('name');
                $startsOn = (string) $this->input('starts_on');
                $endsOn = (string) $this->input('ends_on');

                $duplicateNameExists = AcademicSession::query()
                    ->where('school_id', $user->school_id)
                    ->where('name', $name)
                    ->when(
                        $ignoreAcademicSession instanceof AcademicSession,
                        fn ($query) => $query->whereKeyNot($ignoreAcademicSession->id),
                    )
                    ->exists();

                if ($duplicateNameExists) {
                    $validator->errors()->add('name', 'This school already has an academic session with this name.');
                }

                $overlappingSession = AcademicSession::query()
                    ->where('school_id', $user->school_id)
                    ->when(
                        $ignoreAcademicSession instanceof AcademicSession,
                        fn ($query) => $query->whereKeyNot($ignoreAcademicSession->id),
                    )
                    ->whereDate('starts_on', '<=', $endsOn)
                    ->whereDate('ends_on', '>=', $startsOn)
                    ->first();

                if ($overlappingSession instanceof AcademicSession) {
                    $validator->errors()->add(
                        'starts_on',
                        "These dates overlap {$overlappingSession->name}. Choose dates that do not touch another session.",
                    );
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => Str::of((string) $this->input('name'))->squish()->toString(),
        ]);
    }
}
