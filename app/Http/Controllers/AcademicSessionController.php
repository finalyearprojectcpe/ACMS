<?php

namespace App\Http\Controllers;

use App\Http\Requests\AcademicSessionRequest;
use App\Models\AcademicSession;
use App\Models\AcademicSessionSemester;
use App\Models\Semester;
use App\Models\StudentSet;
use App\Services\DashboardAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

class AcademicSessionController extends Controller
{
    public function __construct(private readonly DashboardAccess $access) {}

    public function store(
        AcademicSessionRequest $request,
        string $departmentSlug,
        string $entrySession,
    ): RedirectResponse {
        $set = $this->ownedSet($request, $departmentSlug, $entrySession);
        $validated = $request->validated();

        $this->ensureUniqueSession($set, $validated);

        $session = DB::transaction(function () use ($set, $validated): AcademicSession {
            $session = $set->academicSessions()->create($this->sessionAttributes($validated));
            $this->syncSemesters($session, $validated);

            if ($set->current_semester_id === null) {
                $set->update([
                    'current_academic_year_id' => $session->academic_year_id,
                    'current_semester_id' => Semester::query()->where('slug', 'first')->firstOrFail()->id,
                ]);
            }

            return $session;
        });

        return to_route('student-sets.edit', $set->routeParameters())
            ->with('success', "{$session->label()} Session added.");
    }

    public function update(
        AcademicSessionRequest $request,
        string $departmentSlug,
        string $entrySession,
        string $sessionYears,
    ): RedirectResponse {
        $set = $this->ownedSet($request, $departmentSlug, $entrySession);
        $academicSession = $this->access->academicSessionByYears($set, $sessionYears);
        $validated = $request->validated();

        $this->ensureUniqueSession($set, $validated, $academicSession);
        $wasCurrent = (int) $set->current_academic_year_id === (int) $academicSession->academic_year_id;

        DB::transaction(function () use ($academicSession, $set, $validated, $wasCurrent): void {
            $academicSession->update($this->sessionAttributes($validated));
            $this->syncSemesters($academicSession, $validated);

            if ($wasCurrent) {
                $set->update(['current_academic_year_id' => $validated['academic_year_id']]);
            }
        });

        return to_route('student-sets.edit', $set->routeParameters())
            ->with('success', "{$academicSession->label()} Session saved.");
    }

    public function destroy(
        Request $request,
        string $departmentSlug,
        string $entrySession,
        string $sessionYears,
    ): RedirectResponse {
        $set = $this->ownedSet($request, $departmentSlug, $entrySession);
        $academicSession = $this->access->academicSessionByYears($set, $sessionYears);

        if ((int) $set->current_academic_year_id === (int) $academicSession->academic_year_id) {
            return back()->with('error', 'Choose another current session before removing this one.');
        }

        if ($academicSession->hasAcademicRecords()) {
            return back()->with('error', 'This session contains courses, registrations, or dues, so it cannot be removed.');
        }

        $label = $academicSession->label();
        $academicSession->delete();

        return to_route('student-sets.edit', $set->routeParameters())
            ->with('success', "{$label} Session removed.");
    }

    private function ownedSet(Request $request, string $departmentSlug, string $entrySession): StudentSet
    {
        return $this->access->studentSetByRoute(
            $this->access->advisor($request),
            $departmentSlug,
            $entrySession,
        );
    }

    /**
     * @param  array<string, int>  $validated
     * @return array{academic_year_id: int, start_year: int, end_year: int}
     */
    private function sessionAttributes(array $validated): array
    {
        return [
            'academic_year_id' => (int) $validated['academic_year_id'],
            'start_year' => (int) $validated['start_year'],
            'end_year' => (int) $validated['end_year'],
        ];
    }

    /** @param array<string, int> $validated */
    private function syncSemesters(AcademicSession $session, array $validated): void
    {
        $semesters = $this->semesterDefinitions();
        $limits = [
            'first' => [
                'field' => 'first_semester_maximum_course_units',
                'units' => (int) $validated['first_semester_maximum_course_units'],
            ],
            'second' => [
                'field' => 'second_semester_maximum_course_units',
                'units' => (int) $validated['second_semester_maximum_course_units'],
            ],
        ];

        foreach ($limits as $slug => $limit) {
            $semesterDefinition = $semesters->get($slug);

            if (! $semesterDefinition instanceof Semester) {
                throw new LogicException("The {$slug} semester definition is missing.");
            }

            $semester = $session->semesterInstances()
                ->where('semester_id', $semesterDefinition->id)
                ->first();

            if ($semester instanceof AcademicSessionSemester) {
                $largestCourseLoad = (int) ($semester->courseRegistrations()
                    ->withSum('items', 'credit_units')
                    ->get()
                    ->max('items_sum_credit_units') ?? 0);

                if ($limit['units'] < $largestCourseLoad) {
                    throw ValidationException::withMessages([
                        $limit['field'] => "{$semesterDefinition->name} already has a registration with {$largestCourseLoad} units.",
                    ]);
                }
            }

            $session->semesterInstances()->updateOrCreate(
                ['semester_id' => $semesterDefinition->id],
                ['maximum_course_units' => $limit['units']],
            );
        }
    }

    /** @return Collection<string, Semester> */
    private function semesterDefinitions(): Collection
    {
        return Semester::query()
            ->whereIn('slug', ['first', 'second'])
            ->get()
            ->keyBy('slug');
    }

    /** @param array{academic_year_id: int, start_year: int, end_year: int} $validated */
    private function ensureUniqueSession(
        StudentSet $set,
        array $validated,
        ?AcademicSession $except = null,
    ): void {
        $sessions = $set->academicSessions()
            ->when($except instanceof AcademicSession, fn ($query) => $query->whereKeyNot($except->id));

        if ((clone $sessions)->where('academic_year_id', $validated['academic_year_id'])->exists()) {
            throw ValidationException::withMessages([
                'academic_year_id' => 'This set already has a session for the selected level.',
            ]);
        }

        if ((clone $sessions)
            ->where('start_year', $validated['start_year'])
            ->where('end_year', $validated['end_year'])
            ->exists()) {
            throw ValidationException::withMessages([
                'start_year' => 'This session already exists for the set.',
            ]);
        }
    }
}
