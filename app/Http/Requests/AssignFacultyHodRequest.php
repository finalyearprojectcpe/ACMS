<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AssignFacultyHodRequest extends FormRequest
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
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'staff_profile_id' => ['required', 'integer', 'exists:staff_profiles,id'],
            'redirect_section' => ['nullable', 'string', 'in:departments,staff'],
        ];
    }
}
