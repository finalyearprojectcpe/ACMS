<?php

namespace App\Http\Requests;

use App\AccountType;
use App\Models\SchoolAdminTransfer;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

class StoreSchoolAdminTransferRequest extends FormRequest
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
            'email' => ['required', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
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

                $email = (string) $this->input('email');

                if ($user->email !== null && hash_equals($user->email, $email)) {
                    $validator->errors()->add('email', 'Enter the email address of the person who will take over this school admin account.');
                }

                $emailAlreadyBelongsToSchoolAccount = User::query()
                    ->where('school_id', $user->school_id)
                    ->where('email', $email)
                    ->exists();

                if ($emailAlreadyBelongsToSchoolAccount) {
                    $validator->errors()->add('email', 'Use an email address that is not already attached to another account in this school.');
                }

                $hasPendingTransfer = SchoolAdminTransfer::query()
                    ->where('school_id', $user->school_id)
                    ->where('status', 'pending')
                    ->where('expires_at', '>', now())
                    ->exists();

                if ($hasPendingTransfer) {
                    $validator->errors()->add('email', 'Cancel the pending ownership invite before sending another one.');
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $name = Str::of((string) $this->input('name'))->squish()->title()->toString();

        $this->merge([
            'email' => Str::of((string) $this->input('email'))->trim()->lower()->toString(),
            'name' => $name === '' ? null : $name,
        ]);
    }
}
