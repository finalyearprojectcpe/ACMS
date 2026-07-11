<?php

namespace App\Http\Requests;

use App\Models\StaffProfile;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AssignStudentSetAdvisorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->staffProfile?->headedDepartment !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'staff_profile_id' => ['required', 'integer', 'exists:'.StaffProfile::class.',id'],
        ];
    }
}
