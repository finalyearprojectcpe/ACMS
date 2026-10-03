<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveStudentRosterEntryRequest;
use App\Models\EmailDelivery;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\DashboardAccess;
use App\Services\EmailDeliveryService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class StudentRosterController extends Controller
{
    public function __construct(
        private readonly DashboardAccess $access,
        private readonly EmailDeliveryService $emails,
    ) {}

    public function store(SaveStudentRosterEntryRequest $request, string $departmentSlug, string $entrySession): RedirectResponse
    {
        $advisor = $this->access->advisor($request);
        $set = $this->access->studentSetByRoute($advisor, $departmentSlug, $entrySession);
        $set->students()->create([
            'official_name' => $request->validated('official_name'),
            'registration_number' => $request->validated('registration_number'),
            'advisor_profile_id' => $advisor->id,
        ]);

        return to_route('student-sets.show', $set->routeParameters())->with('success', 'Student added to the class list.');
    }

    public function update(SaveStudentRosterEntryRequest $request, string $departmentSlug, string $entrySession, string $registrationNumber): RedirectResponse
    {
        $advisor = $this->access->advisor($request);
        $set = $this->access->studentSetByRoute($advisor, $departmentSlug, $entrySession);
        $student = $set->students()
            ->where('registration_number_key', StudentProfile::registrationKey(StudentProfile::registrationNumberFromRoute($registrationNumber)))
            ->firstOrFail();

        $data = $request->validated();
        $newEmail = $student->user !== null ? ($data['email'] ?? null) : null;
        if ($newEmail !== null && $newEmail !== $student->user->email
            && User::query()->where('pending_email', $newEmail)->where('id', '!=', $student->user_id)->exists()) {
            throw ValidationException::withMessages(['email' => 'Another account is already verifying this address.']);
        }

        try {
            $outcome = DB::transaction(function () use ($advisor, $set, $student, $data, $newEmail): string {
                $student->update([
                    'official_name' => $data['official_name'],
                    'registration_number' => $data['registration_number'],
                ]);
                $student->user?->update(['name' => $student->official_name]);

                if ($newEmail !== null && $newEmail !== $student->user->email) {
                    $student->user->update(['pending_email' => $newEmail]);
                    $path = URL::temporarySignedRoute('student-email-changes.verify', now()->addDay(), [
                        'user' => $student->user->public_id,
                        'hash' => sha1($newEmail),
                    ], absolute: false);
                    $delivery = EmailDelivery::query()->create([
                        'advisor_profile_id' => $advisor->id,
                        'user_id' => $student->user_id,
                        'recipient_email' => $newEmail,
                        'subject' => 'Confirm your new ACMS email address',
                        'purpose_key' => 'student-email-change-'.$student->user->public_id.'-'.Str::uuid(),
                    ]);
                    $this->emails->sendStudentJoin($delivery, [
                        'template' => 'student_join',
                        'kind' => 'email_change',
                        'student_name' => $student->official_name,
                        'set_name' => $set->label(),
                        'url' => rtrim(config('app.url'), '/').$path,
                    ]);

                    return 'A verification link was sent to the new email address.';
                }

                if ($student->user?->pending_email !== null) {
                    $student->user->update(['pending_email' => null]);

                    return 'The pending email change was cancelled.';
                }

                return '';
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['email' => 'That email or registration number is already in use.']);
        }

        return to_route('student-sets.show', $set->routeParameters())
            ->with('success', trim('Student details saved. '.$outcome));
    }

    public function verifyEmailChange(Request $request, User $user, string $hash): InertiaResponse
    {
        abort_unless(is_string($user->pending_email) && hash_equals(sha1($user->pending_email), $hash), 403);
        abort_if(User::query()->where('email', $user->pending_email)->where('id', '!=', $user->id)->exists(), 409);

        $user->forceFill([
            'email' => $user->pending_email,
            'pending_email' => null,
            'email_verified_at' => now(),
        ])->save();

        return Inertia::render('student-email-verification', [
            'status' => 'verified',
            'signedInAsAnotherAccount' => $request->user() !== null && ! $request->user()->is($user),
        ]);
    }
}
