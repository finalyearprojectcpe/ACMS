<?php

namespace App\Http\Requests;

use App\AccountType;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStaffDueRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User
            && $user->account_type === AccountType::Staff
            && $user->staffProfile !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'entry_academic_session_id' => ['required', 'integer', 'exists:academic_sessions,id'],
            'title' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:160'],
            'amount_naira' => ['required', 'integer', 'min:100', 'max:1000000'],
            'due_on' => ['nullable', 'date'],
            'action' => ['required', Rule::in(['draft', 'publish'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'entry_academic_session_id.required' => 'Select the student set that should receive this due.',
            'amount_naira.required' => 'Enter the amount students should pay.',
            'amount_naira.min' => 'The due amount should be at least NGN 100.',
            'amount_naira.max' => 'The due amount looks too high. Check it and try again.',
        ];
    }
}
