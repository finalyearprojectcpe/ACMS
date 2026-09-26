<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveCourseGradesRequest;
use App\Models\AcademicSessionSemester;
use App\Models\StudentSet;
use App\Services\DashboardAccess;
use App\Services\ResultService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ResultController extends Controller
{
    public function __construct(
        private readonly DashboardAccess $access,
        private readonly ResultService $results,
    ) {}

    public function saveGrades(
        SaveCourseGradesRequest $request,
        string $departmentSlug,
        string $entrySession,
        string $sessionYears,
        string $semester,
        string $courseCode,
    ): RedirectResponse {
        [$set, $period] = $this->period($request, $departmentSlug, $entrySession, $sessionYears, $semester);
        $courseOffering = $this->access->courseOfferingByRoute($set, $period, $courseCode);
        $this->results->saveGrades($period, $courseOffering, $request->user()->id, $request->validated('grades'));

        return back()->with('success', $period->results_published_at === null
            ? 'Grades saved. Students cannot see them until you publish this semester.'
            : 'Published grades corrected. Students can see the updated results.');
    }

    public function publish(
        Request $request,
        string $departmentSlug,
        string $entrySession,
        string $sessionYears,
        string $semester,
    ): RedirectResponse {
        [$set, $period] = $this->period($request, $departmentSlug, $entrySession, $sessionYears, $semester);
        $this->results->publish($period);

        return to_route('student-sets.results.semester', [
            ...$set->routeParameters(),
            'sessionYears' => $sessionYears,
            'semester' => $semester,
        ])->with('success', 'Semester results published. Students can now see their grades.');
    }

    /** @return array{StudentSet, AcademicSessionSemester} */
    private function period(Request $request, string $departmentSlug, string $entrySession, string $sessionYears, string $semester): array
    {
        $set = $this->access->studentSetByRoute($this->access->advisor($request), $departmentSlug, $entrySession);

        return [$set, $this->access->semesterByRoute($set, $sessionYears, $semester)];
    }
}
