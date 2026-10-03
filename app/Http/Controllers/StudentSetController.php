<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStudentSetRequest;
use App\Http\Requests\UpdateStudentSetRequest;
use App\Models\StudentSet;
use App\Services\DashboardAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class StudentSetController extends Controller
{
    public function __construct(private readonly DashboardAccess $access) {}

    public function store(StoreStudentSetRequest $request): RedirectResponse
    {
        $advisor = $this->access->advisor($request);
        $validated = $request->validated();

        $logoPath = null;

        try {
            $studentSet = DB::transaction(function () use ($advisor, $request, $validated, &$logoPath): StudentSet {
                $studentSet = $advisor->studentSets()->create([
                    'department_name' => $validated['department_name'],
                    'department_slug' => $validated['department_slug'],
                    'entry_start_year' => $validated['start_year'],
                    'entry_end_year' => $validated['end_year'],
                ]);

                if ($request->hasFile('logo')) {
                    $logoPath = $request->file('logo')->store('set-logos/'.$studentSet->id, 'local');
                    if (! is_string($logoPath)) {
                        throw new RuntimeException('The logo could not be stored.');
                    }

                    $studentSet->forceFill(['logo_path' => $logoPath])->save();
                }

                return $studentSet;
            });
        } catch (Throwable $exception) {
            if (is_string($logoPath)) {
                Storage::disk('local')->delete($logoPath);
            }

            throw $exception;
        }

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
        if (array_key_exists('current_semester_id', $validated) && ! array_key_exists('current_academic_year_id', $validated)) {
            throw ValidationException::withMessages([
                'current_academic_year_id' => 'Choose the session together with its semester.',
            ]);
        }

        if (array_key_exists('current_academic_year_id', $validated)) {
            $session = $studentSet->academicSessions()->where('academic_year_id', $validated['current_academic_year_id'])->first();

            if ($session === null) {
                throw ValidationException::withMessages([
                    'current_academic_year_id' => 'Add a session for this level before making it current.',
                ]);
            }

            if (! $session->semesterInstances()->where('semester_id', $validated['current_semester_id'])->exists()) {
                throw ValidationException::withMessages([
                    'current_semester_id' => 'Choose a semester in this session.',
                ]);
            }
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
