<?php

namespace App\Services;

use App\Models\AdvisorProfile;
use App\Models\EmailDelivery;
use App\Models\StudentJoinRequest;
use App\Models\StudentProfile;
use App\Models\StudentSet;
use App\Models\User;
use App\StudentStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StudentJoinService
{
    public function __construct(
        private readonly DueAssignmentService $dues,
        private readonly EmailDeliveryService $emails,
    ) {}

    public function submit(User $user, StudentSet $set, string $registrationNumber): StudentJoinRequest
    {
        $request = StudentJoinRequest::query()->create([
            'user_id' => $user->id,
            'student_set_id' => $set->id,
            'registration_number' => $registrationNumber,
            'status' => StudentJoinRequest::StatusPending,
        ]);

        $this->sendVerification($request);

        return $request;
    }

    public function sendVerification(StudentJoinRequest $request): void
    {
        $request->loadMissing('user', 'studentSet');

        if ($request->user->email_verified_at !== null) {
            return;
        }

        $url = URL::temporarySignedRoute('student-joins.verify', now()->addDay(), [
            'user' => $request->user->public_id,
            'hash' => sha1($request->user->email),
        ]);

        $this->send($request, $request->user, 'Verify your email for GradeManager', 'verification', [
            'url' => $url,
        ], 'join-verification-'.$request->id.'-'.Str::uuid());
    }

    public function verify(User $user): void
    {
        if ($user->email_verified_at !== null) {
            return;
        }

        $user->forceFill(['email_verified_at' => now()])->save();
        $request = $user->studentJoinRequest()->with('studentSet.advisorProfile.user')->firstOrFail();

        if ($request->status === StudentJoinRequest::StatusPending) {
            $this->notifyAdviser($request);
        }
    }

    /** @param array{name: string, email: string, registration_number: string} $details */
    public function resubmit(StudentJoinRequest $request, array $details): void
    {
        DB::transaction(function () use ($request, $details): void {
            $request = StudentJoinRequest::query()->lockForUpdate()->findOrFail($request->id);
            $request->loadMissing('studentSet', 'user');

            $canCorrect = $request->status === StudentJoinRequest::StatusRejected
                || ($request->status === StudentJoinRequest::StatusPending && $request->user->email_verified_at === null);

            if (! $canCorrect || ! $request->studentSet->student_signup_enabled) {
                throw ValidationException::withMessages(['registration_number' => 'This request cannot be resubmitted. Ask your course adviser for help.']);
            }

            $emailChanged = $request->user->email !== $details['email'];
            $request->user->update([
                'name' => $details['name'],
                'email' => $details['email'],
                'email_verified_at' => $emailChanged ? null : $request->user->email_verified_at,
            ]);
            $request->update([
                'registration_number' => $details['registration_number'],
                'status' => StudentJoinRequest::StatusPending,
                'submission_number' => $request->submission_number + 1,
                'reviewed_by_user_id' => null,
                'reviewed_at' => null,
                'review_note' => null,
            ]);

            if ($request->user->email_verified_at === null) {
                $this->sendVerification($request);
            } else {
                $this->notifyAdviser($request);
            }
        });
    }

    public function approve(AdvisorProfile $advisor, StudentJoinRequest $request): void
    {
        DB::transaction(function () use ($advisor, $request): void {
            $request = StudentJoinRequest::query()->lockForUpdate()->findOrFail($request->id);
            $request->loadMissing('studentSet', 'user');

            abort_unless($request->studentSet->advisor_profile_id === $advisor->id, 404);

            if ($request->status !== StudentJoinRequest::StatusPending || $request->user->email_verified_at === null) {
                throw ValidationException::withMessages(['request' => 'This request is not ready for approval.']);
            }

            if ($request->user->studentProfile()->exists() || StudentProfile::query()
                ->where('advisor_profile_id', $advisor->id)
                ->where('registration_number', $request->registration_number)
                ->exists()) {
                throw ValidationException::withMessages(['request' => 'This registration number is already assigned to a student.']);
            }

            $student = StudentProfile::query()->create([
                'user_id' => $request->user_id,
                'advisor_profile_id' => $advisor->id,
                'student_set_id' => $request->student_set_id,
                'registration_number' => $request->registration_number,
                'status' => StudentStatus::Active,
            ]);
            $this->dues->syncForStudent($student);
            $request->update([
                'status' => StudentJoinRequest::StatusApproved,
                'reviewed_by_user_id' => $advisor->user_id,
                'reviewed_at' => now(),
            ]);

            $this->send($request, $request->user, 'Your student account is ready', 'approved', [
                'url' => route('dashboard'),
            ], 'join-decision-'.$request->id.'-'.$request->submission_number);
        });
    }

    public function reject(AdvisorProfile $advisor, StudentJoinRequest $request, string $reason): void
    {
        DB::transaction(function () use ($advisor, $request, $reason): void {
            $request = StudentJoinRequest::query()->lockForUpdate()->findOrFail($request->id);
            $request->loadMissing('studentSet', 'user');

            abort_unless($request->studentSet->advisor_profile_id === $advisor->id, 404);

            if ($request->status !== StudentJoinRequest::StatusPending) {
                throw ValidationException::withMessages(['request' => 'This request has already been reviewed.']);
            }

            $request->update([
                'status' => StudentJoinRequest::StatusRejected,
                'reviewed_by_user_id' => $advisor->user_id,
                'reviewed_at' => now(),
                'review_note' => $reason,
            ]);

            $this->send($request, $request->user, 'Update on your student request', 'rejected', [
                'reason' => $reason,
                'url' => route('student-joins.status'),
            ], 'join-decision-'.$request->id.'-'.$request->submission_number);
        });
    }

    private function notifyAdviser(StudentJoinRequest $request): void
    {
        $request->loadMissing('studentSet.advisorProfile.user', 'user');
        $adviser = $request->studentSet->advisorProfile->user;

        $this->send($request, $adviser, 'Student request awaiting your review', 'adviser_review', [
            'url' => route('student-joins.adviser.show', ['studentJoinRequest' => $request]),
        ], 'join-adviser-'.$request->id.'-'.$request->submission_number);
    }

    /** @param array<string, string> $details */
    private function send(StudentJoinRequest $request, User $recipient, string $subject, string $template, array $details, string $purpose): void
    {
        $delivery = EmailDelivery::query()->firstOrCreate(['purpose_key' => $purpose], [
            'advisor_profile_id' => $request->studentSet->advisor_profile_id,
            'user_id' => $recipient->id,
            'recipient_email' => $recipient->email,
            'subject' => $subject,
        ]);

        $this->emails->sendStudentJoin($delivery, [
            'template' => 'student_join',
            'kind' => $template,
            'student_name' => $request->user->name,
            'set_name' => $request->studentSet->label(),
            ...$details,
        ]);
    }
}
