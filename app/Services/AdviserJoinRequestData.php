<?php

namespace App\Services;

use App\Models\AdvisorProfile;
use App\Models\StudentJoinRequest;
use App\Models\StudentProfile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class AdviserJoinRequestData
{
    public function home(AdvisorProfile $advisor): array
    {
        $query = $this->query($advisor)
            ->where('status', StudentJoinRequest::StatusPending);

        return [
            'requestCount' => (clone $query)->count(),
            'recentRequests' => $query->latest('updated_at')->limit(5)->get()->map($this->row(...))->all(),
        ];
    }

    public function listing(AdvisorProfile $advisor, Request $request): array
    {
        $status = $request->query('status') === 'reviewed' ? 'reviewed' : 'pending';
        $rows = $this->query($advisor)
            ->when($status === 'pending', fn (Builder $query) => $query->where('status', StudentJoinRequest::StatusPending))
            ->when($status === 'reviewed', fn (Builder $query) => $query->whereIn('status', [StudentJoinRequest::StatusApproved, StudentJoinRequest::StatusRejected]))
            ->latest('updated_at')->paginate(15)->withQueryString();

        return [
            'status' => $status,
            'requests' => $rows->through($this->row(...)),
        ];
    }

    public function detail(AdvisorProfile $advisor, StudentJoinRequest $request, bool $rejecting = false): array
    {
        $request = $this->query($advisor)->whereKey($request->id)->firstOrFail();
        $conflict = StudentProfile::query()
            ->where('advisor_profile_id', $advisor->id)
            ->where('registration_number', $request->registration_number)
            ->where('user_id', '!=', $request->user_id)
            ->exists();

        return [
            ...$this->row($request),
            'email' => $request->user->email,
            'emailVerified' => $request->user->email_verified_at !== null,
            'conflict' => $conflict,
            'reason' => $request->review_note,
            'reviewedAt' => $request->reviewed_at?->toFormattedDateString(),
            'reviewer' => $request->reviewer?->displayName(),
            'rejecting' => $rejecting,
            'canApprove' => $request->status === StudentJoinRequest::StatusPending
                && $request->user->email_verified_at !== null && ! $conflict,
            'profileUrl' => $request->status === StudentJoinRequest::StatusApproved
                ? route('dashboard.students.show', ['registrationNumber' => StudentProfile::registrationNumberForRoute($request->registration_number)]) : null,
        ];
    }

    /** @return Builder<StudentJoinRequest> */
    private function query(AdvisorProfile $advisor): Builder
    {
        return StudentJoinRequest::query()
            ->with(['user', 'studentSet', 'reviewer'])
            ->whereHas('studentSet', fn (Builder $sets) => $sets->where('advisor_profile_id', $advisor->id));
    }

    private function row(StudentJoinRequest $request): array
    {
        return [
            'id' => $request->public_id,
            'name' => $request->user->name,
            'registrationNumber' => $request->registration_number,
            'set' => $request->studentSet->label(),
            'status' => $request->status,
            'emailVerified' => $request->user->email_verified_at !== null,
            'requestedAt' => ($request->status === StudentJoinRequest::StatusPending
                ? $request->updated_at : $request->created_at)->toFormattedDateString(),
            'url' => route('student-joins.adviser.show', ['studentJoinRequest' => $request]),
        ];
    }
}
