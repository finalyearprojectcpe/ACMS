<?php

namespace App\Services;

use App\Models\AdvisorProfile;
use App\Models\StudentJoinRequest;
use Illuminate\Database\Eloquent\Builder;

class AdviserJoinRequestData
{
    public function home(AdvisorProfile $advisor): array
    {
        $query = $this->query($advisor)
            ->where('status', StudentJoinRequest::StatusPending)
            ->whereHas('user', fn (Builder $users) => $users->whereNotNull('email_verified_at'));

        return [
            'requestCount' => (clone $query)->count(),
            'recentRequests' => $query->latest('updated_at')->limit(5)->get()->map($this->row(...))->all(),
        ];
    }

    public function detail(AdvisorProfile $advisor, StudentJoinRequest $request): array
    {
        $request = $this->query($advisor)->whereKey($request->id)->firstOrFail();

        return [
            ...$this->row($request),
        ];
    }

    /** @return Builder<StudentJoinRequest> */
    private function query(AdvisorProfile $advisor): Builder
    {
        return StudentJoinRequest::query()
            ->with(['user', 'studentProfile.studentSet', 'reviewer'])
            ->whereHas('studentProfile', fn (Builder $students) => $students->where('advisor_profile_id', $advisor->id));
    }

    private function row(StudentJoinRequest $request): array
    {
        return [
            'id' => $request->public_id,
            'name' => $request->studentProfile->official_name,
            'registrationNumber' => $request->studentProfile->registration_number,
            'set' => $request->studentProfile->studentSet->label(),
            'requestedAt' => ($request->status === StudentJoinRequest::StatusPending
                ? $request->updated_at : $request->created_at)->toFormattedDateString(),
            'url' => route('student-sets.show', $request->studentProfile->studentSet->routeParameters()),
        ];
    }
}
