<?php

namespace App\Http\Controllers\Auth;

use App\AccountStatus;
use App\AccountType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterAdvisorRequest;
use App\Http\Requests\Auth\RegisterStudentRequest;
use App\Http\Requests\Auth\VerifyStudentRegistrationRequest;
use App\Models\AdvisorProfile;
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
        return Inertia::render('signup', [
            'accountType' => AccountType::Student->value,
            'activation' => $this->activationState($request),
        ]);
    }

    public function createAdvisor(): InertiaResponse
    {
        return Inertia::render('signup', [
            'accountType' => AccountType::Adviser->value,
            'activation' => null,
        ]);
    }

    public function verifyStudent(VerifyStudentRegistrationRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $studentProfile = StudentProfile::query()
            ->with('user')
            ->where('registration_number', $validated['registration_number'])
            ->get()
            ->first(function (StudentProfile $profile) use ($validated): bool {
                $user = $profile->user;

                return $user instanceof User
                    && $user->account_type === AccountType::Student
                    && $user->access_code !== null
                    && hash_equals($user->access_code, $validated['access_code']);
            });

        if (! $studentProfile?->user instanceof User) {
            throw ValidationException::withMessages([
                'access_code' => 'The registration number and access code do not match an available student account.',
            ]);
        }

        $this->ensureAccessCodeIsValid($studentProfile->user, $validated['access_code']);
        $this->rememberActivation($request, $studentProfile->user, $studentProfile->registration_number);

        return to_route('signup.student');
    }

    public function storeStudent(RegisterStudentRequest $request): RedirectResponse
    {
        $user = $this->activationUser($request);

        if (! ($user instanceof User)) {
            throw ValidationException::withMessages([
                'password' => 'Verify your registration number and access code before setting your password.',
            ]);
        }

        $validated = $request->validated();

        DB::transaction(function () use ($user, $validated): void {
            $user->forceFill([
                'email' => $validated['email'],
                'password' => $validated['password'],
                'access_code_used_at' => now(),
                'activated_at' => now(),
                'disabled_at' => null,
                'status' => AccountStatus::Active,
            ])->save();
        });

        $this->forgetActivation($request);
        Auth::login($user);
        $request->session()->regenerate();

        return to_route('dashboard');
    }

    public function storeAdvisor(RegisterAdvisorRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $user = DB::transaction(function () use ($validated): User {
            $user = User::query()->create([
                'account_type' => AccountType::Adviser,
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'email_verified_at' => now(),
                'activated_at' => now(),
                'status' => AccountStatus::Active,
            ]);

            AdvisorProfile::query()->create([
                'user_id' => $user->id,
                'institution_name' => $validated['institution_name'] ?? null,
                'programme_name' => $validated['programme_name'],
                'programme_abbreviation' => $validated['programme_abbreviation'],
                'minimum_years_to_degree' => $validated['minimum_years_to_degree'],
                'maximum_course_units' => $validated['maximum_course_units'],
            ]);

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return to_route('dashboard');
    }

    private function ensureAccessCodeIsValid(User $user, string $accessCode): void
    {
        if ($user->activated_at !== null) {
            throw ValidationException::withMessages([
                'registration_number' => 'This student account has already been activated.',
            ]);
        }

        if ($user->access_code === null || $user->access_code_used_at !== null || ! hash_equals($user->access_code, $accessCode)) {
            throw ValidationException::withMessages([
                'access_code' => 'The access code is invalid.',
            ]);
        }
    }

    /** @return array{step: string, identifier: string|null} */
    private function activationState(Request $request): array
    {
        $activation = $this->activationData($request);

        return $activation === null
            ? ['step' => 'verify', 'identifier' => null]
            : ['step' => 'complete', 'identifier' => $activation['identifier']];
    }

    private function activationUser(Request $request): ?User
    {
        $activation = $this->activationData($request);

        if ($activation === null) {
            return null;
        }

        $user = User::query()
            ->whereKey($activation['user_id'])
            ->where('account_type', AccountType::Student->value)
            ->first();

        if ($user === null || $user->activated_at !== null || $user->access_code === null || $user->access_code_used_at !== null) {
            $this->forgetActivation($request);

            return null;
        }

        return $user;
    }

    /** @return array{user_id: int, identifier: string, verified_at: int}|null */
    private function activationData(Request $request): ?array
    {
        $activation = $request->session()->get($this->activationSessionKey());

        if (
            ! is_array($activation)
            || (int) ($activation['verified_at'] ?? 0) < now()->subSeconds(self::ACTIVATION_TTL_SECONDS)->timestamp
            || ! isset($activation['user_id'], $activation['identifier'])
        ) {
            $this->forgetActivation($request);

            return null;
        }

        return [
            'user_id' => (int) $activation['user_id'],
            'identifier' => (string) $activation['identifier'],
            'verified_at' => (int) $activation['verified_at'],
        ];
    }

    private function rememberActivation(Request $request, User $user, string $identifier): void
    {
        $request->session()->put($this->activationSessionKey(), [
            'user_id' => $user->id,
            'identifier' => $identifier,
            'verified_at' => now()->timestamp,
        ]);
    }

    private function forgetActivation(Request $request): void
    {
        $request->session()->forget($this->activationSessionKey());
    }

    private function activationSessionKey(): string
    {
        return 'signup.activation.student';
    }
}
