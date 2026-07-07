<?php

namespace App\Http\Requests;

use App\AccountType;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateStudentProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User
            && $user->account_type === AccountType::Student
            && $user->studentProfile !== null;
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
        ];
    }
}
