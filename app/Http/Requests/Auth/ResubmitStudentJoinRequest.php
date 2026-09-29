<?php

namespace App\Http\Requests\Auth;

use App\Models\StudentJoinRequest;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ResubmitStudentJoinRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $join = $this->user()?->studentJoinRequest;

        return $join?->status === StudentJoinRequest::StatusRejected
            || ($join?->status === StudentJoinRequest::StatusPending && $this->user()?->email_verified_at === null);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'surname' => ['required', 'string', 'max:80'],
            'other_names' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:255', Rule::unique(User::class, 'email')->ignore($this->user()?->id)],
            'registration_number' => ['required', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'surname' => Str::squish((string) $this->input('surname')),
            'other_names' => Str::squish((string) $this->input('other_names')),
            'email' => Str::lower(Str::squish((string) $this->input('email'))),
            'registration_number' => trim((string) $this->input('registration_number')),
        ]);
    }
}
