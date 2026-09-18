<?php

namespace App\Services;

use App\AccountType;
use App\Models\AdvisorProfile;
use App\Models\CourseRegistration;
use App\Models\Due;
use App\Models\DuePayment;
use App\Models\StudentDueAssignment;
use App\Models\StudentProfile;
use App\Models\StudentSet;
use App\Models\User;
use Illuminate\Http\Request;

class DashboardAccess
{
    public function user(Request $request): User
    {
        $user = $request->user();
        abort_unless(
            $user instanceof User
                && in_array($user->account_type, [AccountType::Adviser, AccountType::Student], true),
            403,
        );

        return $user;
    }

    public function advisor(Request $request): AdvisorProfile
    {
        $user = $this->user($request);
        abort_unless(
            $user->isAdviser(),
            403,
        );

        return $user->advisorProfile->loadMissing('user');
    }

    public function student(Request $request): StudentProfile
    {
        $user = $this->user($request);
        abort_unless(
            $user->isStudent(),
            403,
        );

        return $user->studentProfile->loadMissing([
            'user',
            'advisorProfile.user',
            'studentSet.entryAcademicSession',
            'currentLevel',
        ]);
    }

    public function advisorFor(User $user): AdvisorProfile
    {
        $advisor = $user->isAdviser()
            ? $user->advisorProfile
            : $user->studentProfile?->advisorProfile;
        abort_unless($advisor instanceof AdvisorProfile, 404);

        return $advisor->loadMissing('user');
    }

    public function studentSet(AdvisorProfile $advisor, int $studentSetId): StudentSet
    {
        return $advisor->studentSets()
            ->with('entryAcademicSession')
            ->findOrFail($studentSetId);
    }

    public function selectedStudentSet(Request $request, AdvisorProfile $advisor): StudentSet
    {
        $setId = $request->integer('set')
            ?: $request->integer('student_set')
            ?: $request->integer('entry_session');

        return $setId > 0
            ? $this->studentSet($advisor, $setId)
            : $advisor->studentSets()
                ->with('entryAcademicSession')
                ->latest('entry_academic_session_id')
                ->firstOrFail();
    }

    public function ensureOwnsRegistration(
        AdvisorProfile $advisor,
        CourseRegistration $registration,
    ): void {
        abort_unless($registration->advisor_profile_id === $advisor->id, 404);
    }

    public function ensureOwnsDue(AdvisorProfile $advisor, Due $due): void
    {
        abort_unless($due->advisor_profile_id === $advisor->id, 404);
    }

    public function ensureOwnsAssignment(
        AdvisorProfile $advisor,
        Due $due,
        StudentDueAssignment $assignment,
    ): void {
        $this->ensureOwnsDue($advisor, $due);
        abort_unless($assignment->due_id === $due->id, 404);
    }

    public function canViewPayment(User $user, DuePayment $payment): bool
    {
        return ($user->account_type === AccountType::Adviser
                && $user->advisorProfile?->id === $payment->advisor_profile_id)
            || ($user->account_type === AccountType::Student
                && $user->studentProfile?->id === $payment->student_profile_id);
    }
}
