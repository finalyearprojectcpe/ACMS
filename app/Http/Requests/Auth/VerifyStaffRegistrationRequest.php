<?php

namespace App\Http\Requests\Auth;

use App\AccountType;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class VerifyStaffRegistrationRequest extends RegisterAccountRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $school = $this->currentSchool();
        $emailRule = Rule::exists(User::class, 'email')
            ->where('account_type', AccountType::Staff->value);

        if ($school !== null) {
            $emailRule->where('school_id', $school->id);
        }

        return [
            ...$this->accessCodeRules(),
            'email' => ['required', 'string', 'email', 'max:255', $emailRule],
        ];
    }
}
