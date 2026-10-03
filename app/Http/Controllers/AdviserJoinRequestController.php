<?php

namespace App\Http\Controllers;

use App\Http\Requests\RejectStudentJoinRequest;
use App\Models\StudentJoinRequest;
use App\Services\DashboardAccess;
use App\Services\StudentJoinService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdviserJoinRequestController extends Controller
{
    public function __construct(
        private readonly DashboardAccess $access,
        private readonly StudentJoinService $joins,
    ) {}

    public function approve(Request $request, StudentJoinRequest $studentJoinRequest): RedirectResponse
    {
        $advisor = $this->access->advisor($request);
        abort_unless($studentJoinRequest->studentProfile()->where('advisor_profile_id', $advisor->id)->exists(), 404);

        $this->joins->approve($advisor, $studentJoinRequest);

        return to_route('student-sets.show', $studentJoinRequest->studentProfile->studentSet->routeParameters())
            ->with('success', 'Student accepted into the set.');
    }

    public function reject(RejectStudentJoinRequest $request, StudentJoinRequest $studentJoinRequest): RedirectResponse
    {
        $advisor = $this->access->advisor($request);
        abort_unless($studentJoinRequest->studentProfile()->where('advisor_profile_id', $advisor->id)->exists(), 404);

        $this->joins->reject($advisor, $studentJoinRequest, $request->validated('reason'));

        return to_route('student-sets.show', $studentJoinRequest->studentProfile->studentSet->routeParameters())->with('success', 'Applicant notified of the reason.');
    }
}
