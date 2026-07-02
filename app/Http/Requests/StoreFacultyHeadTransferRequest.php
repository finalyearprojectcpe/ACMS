<?php

namespace App\Http\Requests;

use App\AccountType;
use App\Models\Faculty;
use App\Models\FacultyHeadTransfer;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

class StoreFacultyHeadTransferRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User
            && $user->account_type === AccountType::Staff
            && $user->staffProfile?->headedFaculty()->exists();
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

                $user->loadMissing('staffProfile.headedFaculty');
                $faculty = $user->staffProfile?->headedFaculty;

                if (! $faculty instanceof Faculty) {
                    return;
                }

                $email = (string) $this->input('email');
                $recipient = User::query()
                    ->with('staffProfile.department')
                    ->where('school_id', $user->school_id)
                    ->where('account_type', AccountType::Staff)
                    ->where('email', $email)
                    ->first();

                if (! $recipient instanceof User || ! $recipient->staffProfile) {
                    $validator->errors()->add('email', 'Enter the email address of a staff member in your faculty.');

                    return;
                }

                if ((int) $recipient->id === (int) $user->id) {
                    $validator->errors()->add('email', 'Enter the email address of the staff member who will take over as faculty head.');
                }

                if ((int) $recipient->staffProfile->department?->faculty_id !== (int) $faculty->id) {
                    $validator->errors()->add('email', 'Choose a staff member who belongs to your faculty.');
                }

                $hasPendingTransfer = FacultyHeadTransfer::query()
                    ->where('faculty_id', $faculty->id)
                    ->where('status', 'pending')
                    ->where('expires_at', '>', now())
                    ->exists();

                if ($hasPendingTransfer) {
                    $validator->errors()->add('email', 'Cancel the pending faculty head invite before sending another one.');
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
