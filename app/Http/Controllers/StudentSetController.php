<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStudentSetRequest;
use App\Http\Requests\UpdateStudentSetRequest;
use App\Models\StudentSet;
use App\Services\DashboardAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class StudentSetController extends Controller
{
    public function __construct(private readonly DashboardAccess $access) {}

    public function store(StoreStudentSetRequest $request): RedirectResponse
    {
        $advisor = $this->access->advisor($request);
        $validated = $request->validated();

        $studentSet = $advisor->studentSets()->create([
            'department_name' => $validated['department_name'],
            'department_slug' => $validated['department_slug'],
            'entry_start_year' => $validated['start_year'],
            'entry_end_year' => $validated['end_year'],
        ]);

        return to_route('student-sets.show', $studentSet->routeParameters())
            ->with('success', "{$studentSet->label()} added.");
    }

    public function rotateInvite(
        Request $request,
        string $departmentSlug,
        string $entrySession,
    ): RedirectResponse {
        $studentSet = $this->ownedSet($request, $departmentSlug, $entrySession);
        $studentSet->rotateInviteToken();

        return back()->with('success', 'A new student signup link is ready. The previous link no longer works.');
    }

    public function update(
        UpdateStudentSetRequest $request,
        string $departmentSlug,
        string $entrySession,
    ): RedirectResponse {
        $studentSet = $this->ownedSet($request, $departmentSlug, $entrySession);
        $validated = $request->validated();
        $academicYearId = $validated['current_academic_year_id'] ?? null;

        if ($academicYearId !== null && ! $studentSet->academicSessions()
            ->where('academic_year_id', $academicYearId)
            ->exists()) {
            throw ValidationException::withMessages([
                'current_academic_year_id' => 'Add a session for this academic level before making it current.',
            ]);
        }

        $studentSet->update($validated);

        return to_route('student-sets.edit', $studentSet->routeParameters())
            ->with('success', 'Set settings saved.');
    }

    private function ownedSet(
        Request $request,
        string $departmentSlug,
        string $entrySession,
    ): StudentSet {
        return $this->access->studentSetByRoute(
            $this->access->advisor($request),
            $departmentSlug,
            $entrySession,
        );
    }
}
