<?php

namespace App\Http\Controllers;

use App\AccountType;
use App\Http\Requests\AcademicSessionRequest;
use App\Http\Requests\StudentSetRequest;
use App\Models\AcademicSession;
use App\Models\AdvisorProfile;
use App\Models\StudentSet;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AcademicSetupController extends Controller
{
    public function storeSession(AcademicSessionRequest $request): RedirectResponse
    {
        $advisor = $this->authorizedAdvisor($request);
        $validated = $request->validated();
        $this->ensureSessionDoesNotOverlap($advisor, $validated);

        $advisor->academicSessions()->create($validated);

        return to_route('dashboard.section', ['section' => 'profile-settings'])
            ->with('success', "{$validated['name']} Session added.");
    }

    public function updateSession(
        AcademicSessionRequest $request,
        AcademicSession $academicSession,
    ): RedirectResponse {
        $advisor = $this->authorizedAdvisor($request);
        abort_unless($academicSession->advisor_profile_id === $advisor->id, 404);
        $validated = $request->validated();
        $this->ensureSessionDoesNotOverlap($advisor, $validated, $academicSession);

        $academicSession->update($validated);

        return to_route('dashboard.section', ['section' => 'profile-settings'])
            ->with('success', "{$academicSession->name} Session updated.");
    }

    public function storeStudentSet(StudentSetRequest $request): RedirectResponse
    {
        $advisor = $this->authorizedAdvisor($request);
        $validated = $request->validated();
        $session = $advisor->academicSessions()->findOrFail($validated['entry_academic_session_id']);

        if ($advisor->studentSets()->whereBelongsTo($session, 'entryAcademicSession')->exists()) {
            throw ValidationException::withMessages([
                'entry_academic_session_id' => 'A student set already uses this entry session.',
            ]);
        }

        $advisor->studentSets()->create([
            'entry_academic_session_id' => $session->id,
            'name' => "{$session->name} Set",
            'minimum_years_to_degree' => $validated['minimum_years_to_degree'],
            'maximum_course_units' => $validated['maximum_course_units'],
        ]);

        return to_route('dashboard.section', ['section' => 'profile-settings'])
            ->with('success', "{$session->name} Set added.");
    }

    public function updateStudentSet(
        StudentSetRequest $request,
        StudentSet $studentSet,
    ): RedirectResponse {
        $advisor = $this->authorizedAdvisor($request);
        abort_unless($studentSet->advisor_profile_id === $advisor->id, 404);
        $validated = $request->validated();

        $studentSet->update([
            'minimum_years_to_degree' => $validated['minimum_years_to_degree'],
            'maximum_course_units' => $validated['maximum_course_units'],
        ]);

        return to_route('dashboard.section', ['section' => 'profile-settings'])
            ->with('success', "{$studentSet->name} updated.");
    }

    private function authorizedAdvisor(Request $request): AdvisorProfile
    {
        $user = $request->user();
        abort_unless(
            $user instanceof User
                && $user->account_type === AccountType::Adviser
                && $user->advisorProfile instanceof AdvisorProfile,
            403,
        );

        return $user->advisorProfile;
    }

    /** @param array{name: string, starts_on: string, ends_on: string} $attributes */
    private function ensureSessionDoesNotOverlap(
        AdvisorProfile $advisor,
        array $attributes,
        ?AcademicSession $ignoredSession = null,
    ): void {
        $sessions = $advisor->academicSessions()
            ->when($ignoredSession, fn ($query) => $query->whereKeyNot($ignoredSession->id))
            ->getQuery();

        if ((clone $sessions)->where('name', $attributes['name'])->exists()) {
            throw ValidationException::withMessages([
                'name' => 'An academic session already uses this name.',
            ]);
        }

        $overlaps = (clone $sessions)
            ->whereDate('starts_on', '<=', $attributes['ends_on'])
            ->whereDate('ends_on', '>=', $attributes['starts_on'])
            ->exists();

        if ($overlaps) {
            throw ValidationException::withMessages([
                'starts_on' => 'These dates overlap another academic session.',
            ]);
        }
    }
}
