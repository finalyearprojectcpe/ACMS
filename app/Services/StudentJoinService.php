<?php

namespace App\Services;

use App\Models\AdvisorProfile;
use App\Models\EmailDelivery;
use App\Models\StudentJoinRequest;
use App\Models\StudentProfile;
use App\Models\User;
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

    public function submit(User $user, StudentProfile $student): StudentJoinRequest
    {
        $this->ensureAvailable($student, field: 'invitation');

        $request = StudentJoinRequest::query()->create([
            'user_id' => $user->id,
            'student_profile_id' => $student->id,
            'active_claim_profile_id' => $student->id,
            'status' => StudentJoinRequest::StatusPending,
        ]);

        $this->sendVerification($request);

        return $request;
    }

    public function ensureAvailable(StudentProfile $student, ?StudentJoinRequest $except = null, string $field = 'registration_number'): void
    {
        if ($student->user_id !== null || $student->joinRequests()
            ->where('status', StudentJoinRequest::StatusPending)
            ->when($except !== null, fn ($query) => $query->where('id', '!=', $except->id))
            ->exists()) {
            throw ValidationException::withMessages([
                $field => 'This student record is already being claimed. Ask your course adviser for help.',
            ]);
        }
    }

    public function sendVerification(StudentJoinRequest $request): void
    {
        $request->loadMissing('user', 'studentProfile.studentSet');

        if ($request->user->email_verified_at !== null) {
            return;
        }

        $path = URL::temporarySignedRoute('student-joins.verify', now()->addDay(), [
            'user' => $request->user->public_id,
            'hash' => sha1($request->user->email),
        ], absolute: false);
        $url = rtrim(config('app.url'), '/').$path;

        $this->send($request, $request->user, 'Verify your email for ACMS', 'verification', [
            'url' => $url,
        ], 'join-verification-'.$request->id.'-'.Str::uuid());
    }

    public function verify(User $user): void
    {
        if ($user->email_verified_at !== null) {
            return;
        }

        $user->forceFill(['email_verified_at' => now()])->save();
        $request = $user->studentJoinRequest()->with('studentProfile.studentSet.advisorProfile.user')->firstOrFail();

        if ($request->status === StudentJoinRequest::StatusPending) {
            $this->notifyAdviser($request);
        }
    }

    /** @param array{email: string, registration_number?: string} $details */
    public function resubmit(StudentJoinRequest $request, array $details): void
    {
        DB::transaction(function () use ($request, $details): void {
            $request = StudentJoinRequest::query()->lockForUpdate()->findOrFail($request->id);
            $request->loadMissing('studentProfile.studentSet', 'user');
            $set = $request->studentProfile->studentSet;
            $canCorrect = $request->status === StudentJoinRequest::StatusRejected
                || ($request->status === StudentJoinRequest::StatusPending && $request->user->email_verified_at === null);

            if (! $canCorrect || ! $set->student_signup_enabled) {
                throw ValidationException::withMessages(['registration_number' => 'This request cannot be updated. Ask your course adviser for help.']);
            }

            $registrationNumber = $request->status === StudentJoinRequest::StatusRejected
                ? $details['registration_number'] ?? ''
                : $request->studentProfile->registration_number;

            $student = $set->students()
                ->where('registration_number_key', StudentProfile::registrationKey($registrationNumber))
                ->lockForUpdate()
                ->first();

            if (! $student instanceof StudentProfile) {
                throw ValidationException::withMessages(['registration_number' => 'That registration number is not on this set’s class list.']);
            }

            $this->ensureAvailable($student, $request);
            $emailChanged = $request->user->email !== $details['email'];
            $request->user->update([
                'name' => $student->official_name,
                'email' => $details['email'],
                'email_verified_at' => $emailChanged ? null : $request->user->email_verified_at,
            ]);
            $request->update([
                'student_profile_id' => $student->id,
                'active_claim_profile_id' => $student->id,
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
            $request->loadMissing('studentProfile.studentSet', 'user');
            $student = StudentProfile::query()->lockForUpdate()->findOrFail($request->student_profile_id);

            abort_unless($student->advisor_profile_id === $advisor->id, 404);

            if ($request->status !== StudentJoinRequest::StatusPending || $request->user->email_verified_at === null) {
                throw ValidationException::withMessages(['request' => 'This claim is not ready to accept.']);
            }

            if ($student->user_id !== null) {
                throw ValidationException::withMessages(['request' => 'This student already has an account.']);
            }

            $student->update(['user_id' => $request->user_id]);
            $request->user->update(['name' => $student->official_name]);
            $this->dues->syncForStudent($student);
            $request->update([
                'status' => StudentJoinRequest::StatusApproved,
                'active_claim_profile_id' => null,
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
            $request->loadMissing('studentProfile.studentSet', 'user');

            abort_unless($request->studentProfile->advisor_profile_id === $advisor->id, 404);

            if ($request->status !== StudentJoinRequest::StatusPending) {
                throw ValidationException::withMessages(['request' => 'This claim has already been reviewed.']);
            }

            $request->update([
                'status' => StudentJoinRequest::StatusRejected,
                'active_claim_profile_id' => null,
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
        $request->loadMissing('studentProfile.studentSet.advisorProfile.user', 'user');
        $adviser = $request->studentProfile->studentSet->advisorProfile->user;

        $this->send($request, $adviser, 'Student claim awaiting your review', 'adviser_review', [
            'url' => route('student-sets.show', $request->studentProfile->studentSet->routeParameters()),
        ], 'join-adviser-'.$request->id.'-'.$request->submission_number);
    }

    /** @param array<string, string> $details */
    private function send(StudentJoinRequest $request, User $recipient, string $subject, string $template, array $details, string $purpose): void
    {
        $request->loadMissing('studentProfile.studentSet');
        $delivery = EmailDelivery::query()->firstOrCreate(['purpose_key' => $purpose], [
            'advisor_profile_id' => $request->studentProfile->advisor_profile_id,
            'user_id' => $recipient->id,
            'recipient_email' => $recipient->email,
            'subject' => $subject,
        ]);

        $this->emails->sendStudentJoin($delivery, [
            'template' => 'student_join',
            'kind' => $template,
            'student_name' => $request->studentProfile->official_name,
            'set_name' => $request->studentProfile->studentSet->label(),
            ...$details,
        ]);
    }
}
