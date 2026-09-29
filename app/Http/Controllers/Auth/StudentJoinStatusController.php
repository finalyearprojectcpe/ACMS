<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResubmitStudentJoinRequest;
use App\Models\StudentJoinRequest;
use App\Models\User;
use App\Services\StudentJoinService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class StudentJoinStatusController extends Controller
{
    public function __construct(private readonly StudentJoinService $joins) {}

    public function show(Request $request): InertiaResponse|RedirectResponse
    {
        $user = $request->user();

        if ($user->studentProfile()->exists()) {
            return to_route('dashboard');
        }

        $join = $user->studentJoinRequest()->with('studentSet')->firstOrFail();

        return Inertia::render('student-join-status', [
            'request' => [
                'set' => $join->studentSet->label(),
                'status' => $join->status,
                'emailVerified' => $user->email_verified_at !== null,
                'email' => $user->email,
                'reason' => $join->review_note,
                'canResubmit' => $join->studentSet->student_signup_enabled
                    && ($join->status === StudentJoinRequest::StatusRejected
                        || ($join->status === StudentJoinRequest::StatusPending && $user->email_verified_at === null)),
            ],
        ]);
    }

    public function edit(Request $request): InertiaResponse
    {
        $join = $request->user()->studentJoinRequest()->with('studentSet')->firstOrFail();
        abort_unless($join->studentSet->student_signup_enabled
            && ($join->status === StudentJoinRequest::StatusRejected
                || ($join->status === StudentJoinRequest::StatusPending && $request->user()->email_verified_at === null)), 404);

        $name = explode(' ', $request->user()->name, 2);

        return Inertia::render('student-join-edit', [
            'request' => [
                'set' => $join->studentSet->label(),
                'surname' => $name[0],
                'otherNames' => $name[1] ?? '',
                'registrationNumber' => $join->registration_number,
                'email' => $request->user()->email,
                'status' => $join->status,
                'reason' => $join->review_note,
            ],
        ]);
    }

    public function update(ResubmitStudentJoinRequest $request): RedirectResponse
    {
        $join = $request->user()->studentJoinRequest()->firstOrFail();
        $data = $request->validated();
        $this->joins->resubmit($join, [
            'name' => $data['surname'].' '.$data['other_names'],
            'email' => $data['email'],
            'registration_number' => $data['registration_number'],
        ]);

        return to_route('student-joins.status')->with('success', $request->user()->refresh()->email_verified_at === null
            ? 'Check your email to verify your updated request.'
            : 'Your corrected request has been sent for review.');
    }

    public function resend(Request $request): RedirectResponse
    {
        $join = $request->user()->studentJoinRequest()->firstOrFail();
        abort_unless($join->status === StudentJoinRequest::StatusPending && $request->user()->email_verified_at === null, 404);

        $this->joins->sendVerification($join);

        return to_route('student-joins.status')->with('success', 'A new verification email is on its way.');
    }

    public function verify(Request $request, User $user, string $hash): RedirectResponse
    {
        abort_unless($request->user()->is($user) && hash_equals(sha1($user->email), $hash), 403);

        $this->joins->verify($user);

        return to_route('student-joins.status')->with('success', 'Email verified. Your course adviser can now review your request.');
    }
}
