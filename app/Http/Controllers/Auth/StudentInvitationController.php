<?php

namespace App\Http\Controllers\Auth;

use App\AccountStatus;
use App\AccountType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterInvitedStudentRequest;
use App\Http\Requests\FindInvitedStudentRequest;
use App\Models\StudentJoinRequest;
use App\Models\StudentProfile;
use App\Models\StudentSet;
use App\Models\User;
use App\Services\StudentJoinService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function show(Request $request, StudentSet $studentSet): InertiaResponse
    {
        $studentSet->loadMissing('advisorProfile.user');
        $student = $this->selectedStudent($request, $studentSet);

        return Inertia::render('student-invitation', [
            'invitation' => [
                'available' => $studentSet->student_signup_enabled,
                'inviteToken' => $studentSet->invite_token,
                'studentSet' => $studentSet->label(),
                'courseAdviser' => $studentSet->advisorProfile->user->displayName(),
                'student' => $student instanceof StudentProfile ? [
                    'name' => $student->official_name,
                    'registrationNumber' => $student->registration_number,
                ] : null,
            ],
        ]);
    }

    public function lookup(FindInvitedStudentRequest $request, StudentSet $studentSet): RedirectResponse
    {
        if (! $studentSet->student_signup_enabled) {
            throw ValidationException::withMessages(['registration_number' => 'This invite is closed. Ask your course adviser for help.']);
        }

        $student = $studentSet->students()
            ->where('registration_number_key', StudentProfile::registrationKey($request->validated('registration_number')))
            ->first();

        if (! $student instanceof StudentProfile) {
            throw ValidationException::withMessages(['registration_number' => 'That registration number is not on this set’s class list. Check it or ask your course adviser.']);
        }

        $this->joins->ensureAvailable($student);
        $request->session()->put('student_invitation_claim', [
            'set_id' => $studentSet->id,
            'student_id' => $student->id,
        ]);

        return to_route('student-invitations.show', ['studentSet' => $studentSet->invite_token]);
    }

    public function clearLookup(Request $request, StudentSet $studentSet): RedirectResponse
    {
        $request->session()->forget('student_invitation_claim');

        return to_route('student-invitations.show', ['studentSet' => $studentSet->invite_token]);
    }

    public function store(
        RegisterInvitedStudentRequest $request,
        StudentSet $studentSet,
    ): RedirectResponse {
        $validated = $request->validated();

        $selected = $request->session()->get('student_invitation_claim');
        if (! is_array($selected) || (int) ($selected['set_id'] ?? 0) !== $studentSet->id) {
            throw ValidationException::withMessages(['invitation' => 'Find your registration number again to continue.']);
        }

        try {
            $user = DB::transaction(function () use ($studentSet, $validated, $selected): User {
                $lockedSet = StudentSet::query()
                    ->lockForUpdate()
                    ->findOrFail($studentSet->id);

                if (! $lockedSet->student_signup_enabled) {
                    throw ValidationException::withMessages([
                        'invitation' => 'This student signup link is currently closed. Ask your course adviser for help.',
                    ]);
                }

                $student = $lockedSet->students()->lockForUpdate()->findOrFail((int) $selected['student_id']);
                $this->joins->ensureAvailable($student, field: 'invitation');

                $user = User::query()->create([
                    'account_type' => AccountType::Student,
                    'name' => $student->official_name,
                    'email' => $validated['email'],
                    'password' => $validated['password'],
                    'status' => AccountStatus::Active,
                ]);
                $this->joins->submit($user, $student);

                return $user;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['invitation' => 'This student record is already being claimed. Ask your course adviser for help.']);
        }

        $request->session()->forget('student_invitation_claim');
        Auth::login($user);
        $request->session()->regenerate();

        return to_route('student-joins.status')->with('success', 'Check your email to verify your request.');
    }

    private function selectedStudent(Request $request, StudentSet $set): ?StudentProfile
    {
        $selected = $request->session()->get('student_invitation_claim');

        if (! is_array($selected) || (int) ($selected['set_id'] ?? 0) !== $set->id) {
            return null;
        }

        $student = $set->students()->find((int) ($selected['student_id'] ?? 0));

        if (! $student instanceof StudentProfile || $student->user_id !== null
            || $student->joinRequests()->where('status', StudentJoinRequest::StatusPending)->exists()) {
            $request->session()->forget('student_invitation_claim');

            return null;
        }

        return $student;
    }
}
