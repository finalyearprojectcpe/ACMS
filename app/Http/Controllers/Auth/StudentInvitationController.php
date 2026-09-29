<?php

namespace App\Http\Controllers\Auth;

use App\AccountStatus;
use App\AccountType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterInvitedStudentRequest;
use App\Models\StudentSet;
use App\Models\User;
use App\Services\StudentJoinService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class StudentInvitationController extends Controller
{
    public function __construct(
        private readonly StudentJoinService $joins,
    ) {}

    public function show(StudentSet $studentSet): InertiaResponse
    {
        $studentSet->loadMissing('advisorProfile.user');

        return Inertia::render('student-invitation', [
            'invitation' => [
                'available' => $studentSet->student_signup_enabled,
                'inviteToken' => $studentSet->invite_token,
                'studentSet' => $studentSet->label(),
                'courseAdviser' => $studentSet->advisorProfile->user->displayName(),
            ],
        ]);
    }

    public function store(
        RegisterInvitedStudentRequest $request,
        StudentSet $studentSet,
    ): RedirectResponse {
        $validated = $request->validated();

        $user = DB::transaction(function () use ($studentSet, $validated): User {
            $lockedSet = StudentSet::query()
                ->lockForUpdate()
                ->findOrFail($studentSet->id);

            if (! $lockedSet->student_signup_enabled) {
                throw ValidationException::withMessages([
                    'invitation' => 'This student signup link is currently closed. Ask your course adviser for help.',
                ]);
            }

            $user = User::query()->create([
                'account_type' => AccountType::Student,
                'name' => $validated['surname'].' '.$validated['other_names'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'status' => AccountStatus::Active,
            ]);
            $this->joins->submit($user, $lockedSet, $validated['registration_number']);

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return to_route('student-joins.status')->with('success', 'Check your email to verify your request.');
    }
}
