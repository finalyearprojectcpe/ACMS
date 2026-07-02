<?php

namespace App\Http\Controllers\Auth;

use App\AccountType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterAccountRequest;
use App\Http\Requests\Auth\RegisterStaffRequest;
use App\Http\Requests\Auth\RegisterStudentRequest;
use App\Http\Requests\Auth\VerifyStaffRegistrationRequest;
use App\Http\Requests\Auth\VerifyStudentRegistrationRequest;
use App\Models\School;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class RegisteredUserController extends Controller
{
    private const int ACTIVATION_TTL_SECONDS = 900;

    public function createStudent(Request $request): InertiaResponse
    {
        return $this->renderSignup($request, AccountType::Student);
    }

    public function createStaff(Request $request): InertiaResponse
    {
        return $this->renderSignup($request, AccountType::Staff);
    }

    public function verifyStudent(VerifyStudentRegistrationRequest $request): RedirectResponse
    {
        $school = $this->currentSchool($request);
        $validated = $request->validated();
        $studentProfile = StudentProfile::query()
            ->with('user')
            ->where('school_id', $school->id)
            ->where('registration_number', $validated['registration_number'])
            ->whereHas('user', function ($query) use ($school): void {
                $query
                    ->where('school_id', $school->id)
                    ->where('account_type', AccountType::Student);
            })
            ->first();

        if ($studentProfile?->user === null) {
            throw ValidationException::withMessages([
                'registration_number' => 'No student account matches this registration number.',
            ]);
        }

        $this->ensureAccessCodeIsValid($studentProfile->user, $validated['access_code'], 'registration_number');
        $this->rememberActivation($request, AccountType::Student, $studentProfile->user, $studentProfile->registration_number);

        return redirect()->route('signup.student');
    }

    public function verifyStaff(VerifyStaffRegistrationRequest $request): RedirectResponse
    {
        $school = $this->currentSchool($request);
        $validated = $request->validated();
        $user = User::query()
            ->where('school_id', $school->id)
            ->where('account_type', AccountType::Staff)
            ->where('email', $validated['email'])
            ->first();

        if ($user === null) {
            throw ValidationException::withMessages([
                'email' => 'No staff account matches this email address.',
            ]);
        }

        $this->ensureAccessCodeIsValid($user, $validated['access_code'], 'email');
        $this->rememberActivation($request, AccountType::Staff, $user, $user->email ?? $validated['email']);

        return redirect()->route('signup.staff');
    }

    public function storeStudent(RegisterStudentRequest $request): RedirectResponse
    {
        $user = $this->activationUser($request, AccountType::Student);

        if ($user === null) {
            throw ValidationException::withMessages([
                'password' => 'Verify your access code before setting your password.',
            ]);
        }

        $validated = $request->validated();

        return $this->activateAccount($request, $user, $validated, [
            'email' => $validated['email'],
        ]);
    }

    public function storeStaff(RegisterStaffRequest $request): RedirectResponse
    {
        $user = $this->activationUser($request, AccountType::Staff);

        if ($user === null) {
            throw ValidationException::withMessages([
                'password' => 'Verify your access code before setting your password.',
            ]);
        }

        $validated = $request->validated();

        return $this->activateAccount($request, $user, $validated);
    }

    private function renderSignup(Request $request, AccountType $accountType): InertiaResponse
    {
        return Inertia::render('signup', [
            'accountType' => $accountType->value,
            'activation' => $this->activationState($request, $accountType),
        ]);
    }

    /**
     * @param  array{password: string}  $validated
     * @param  array<string, string|null>  $attributes
     */
    private function activateAccount(RegisterAccountRequest $request, User $user, array $validated, array $attributes = []): RedirectResponse
    {
        if ($user->activated_at !== null) {
            throw ValidationException::withMessages([
                'password' => 'This account has already been activated.',
            ]);
        }

        if ($user->access_code === null || $user->access_code_used_at !== null) {
            throw ValidationException::withMessages([
                'password' => 'Verify your access code before setting your password.',
            ]);
        }

        DB::transaction(function () use ($attributes, $user, $validated): void {
            $user->forceFill([
                ...$attributes,
                'password' => $validated['password'],
                'access_code_used_at' => now(),
                'activated_at' => now(),
            ])->save();
        });

        $this->forgetActivation($request, $user->account_type);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }

    private function ensureAccessCodeIsValid(User $user, string $accessCode, string $identifierField): void
    {
        if ($user->activated_at !== null) {
            throw ValidationException::withMessages([
                $identifierField => 'This account has already been activated.',
            ]);
        }

        if (
            $user->access_code === null
            || $user->access_code_used_at !== null
            || ! hash_equals((string) $user->access_code, $accessCode)
        ) {
            throw ValidationException::withMessages([
                'access_code' => 'The access code is invalid.',
            ]);
        }
    }

    /**
     * @return array{step: string, identifier: string|null}
     */
    private function activationState(Request $request, AccountType $accountType): array
    {
        $activation = $this->activationData($request, $accountType);

        if ($activation === null) {
            return [
                'step' => 'verify',
                'identifier' => null,
            ];
        }

        return [
            'step' => 'complete',
            'identifier' => (string) $activation['identifier'],
        ];
    }

    private function activationUser(Request $request, AccountType $accountType): ?User
    {
        $school = $this->currentSchool($request);
        $activation = $this->activationData($request, $accountType);

        if ($activation === null) {
            return null;
        }

        $user = User::query()
            ->whereKey($activation['user_id'])
            ->where('school_id', $school->id)
            ->where('account_type', $accountType->value)
            ->first();

        if (
            $user === null
            || $user->activated_at !== null
            || $user->access_code === null
            || $user->access_code_used_at !== null
        ) {
            $this->forgetActivation($request, $accountType);

            return null;
        }

        return $user;
    }

    /**
     * @return array{user_id: int, school_id: int, account_type: string, identifier: string, verified_at: int}|null
     */
    private function activationData(Request $request, AccountType $accountType): ?array
    {
        $school = $this->currentSchool($request);
        $activation = $request->session()->get($this->activationSessionKey($accountType));

        if (! is_array($activation)) {
            return null;
        }

        if (
            (int) ($activation['school_id'] ?? 0) !== $school->id
            || ($activation['account_type'] ?? null) !== $accountType->value
            || (int) ($activation['verified_at'] ?? 0) < now()->subSeconds(self::ACTIVATION_TTL_SECONDS)->timestamp
            || ! isset($activation['user_id'], $activation['identifier'])
        ) {
            $this->forgetActivation($request, $accountType);

            return null;
        }

        return [
            'user_id' => (int) $activation['user_id'],
            'school_id' => (int) $activation['school_id'],
            'account_type' => (string) $activation['account_type'],
            'identifier' => (string) $activation['identifier'],
            'verified_at' => (int) $activation['verified_at'],
        ];
    }

    private function rememberActivation(Request $request, AccountType $accountType, User $user, string $identifier): void
    {
        $request->session()->put($this->activationSessionKey($accountType), [
            'user_id' => $user->id,
            'school_id' => $user->school_id,
            'account_type' => $accountType->value,
            'identifier' => $identifier,
            'verified_at' => now()->timestamp,
        ]);
    }

    private function forgetActivation(Request $request, AccountType $accountType): void
    {
        $request->session()->forget($this->activationSessionKey($accountType));
    }

    private function activationSessionKey(AccountType $accountType): string
    {
        return "signup.activation.{$accountType->value}";
    }

    private function currentSchool(Request $request): School
    {
        $school = $request->attributes->get('school');

        abort_unless($school instanceof School, 404);

        return $school;
    }
}
