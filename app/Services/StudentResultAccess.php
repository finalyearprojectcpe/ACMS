<?php

namespace App\Services;

use App\Models\AcademicSessionSemester;
use App\Models\CourseRegistration;
use App\Models\Due;
use App\Models\StudentDueAssignment;
use App\Models\StudentProfile;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class StudentResultAccess
{
    /**
     * @param  Collection<int, AcademicSessionSemester>  $semesters
     * @return array<int, list<Due>>
     */
    public function blockingDues(StudentProfile $student, Collection $semesters): array
    {
        $semesters = $semesters->unique('id')->values();
        if ($semesters->isEmpty()) {
            return [];
        }

        $dues = Due::query()
            ->where('student_set_id', $student->student_set_id)
            ->where('status', Due::StatusPublished)
            ->whereIn('academic_session_id', $semesters->pluck('academic_session_id'))
            ->with(['assignments' => fn ($query) => $query->where('student_profile_id', $student->id)])
            ->get();

        $blocked = [];
        foreach ($semesters as $semester) {
            $blocked[$semester->id] = $dues
                ->filter(function (Due $due) use ($semester): bool {
                    if ((int) $due->academic_session_id !== (int) $semester->academic_session_id) {
                        return false;
                    }

                    if ($due->academic_session_semester_id !== null
                        && (int) $due->academic_session_semester_id !== (int) $semester->id) {
                        return false;
                    }

                    $assignment = $due->assignments->first();

                    return ! $assignment instanceof StudentDueAssignment
                        || ! in_array($assignment->status, [
                            StudentDueAssignment::StatusPaid,
                            StudentDueAssignment::StatusWaived,
                        ], true);
                })
                ->values()
                ->all();
        }

        return $blocked;
    }

    /** @return list<int> */
    public function accessibleSemesterIds(StudentProfile $student): array
    {
        $semesters = AcademicSessionSemester::query()
            ->whereIn('id', $student->courseRegistrations()
                ->where('status', CourseRegistration::StatusAssigned)
                ->select('academic_session_semester_id'))
            ->get();
        $blocked = $this->blockingDues($student, $semesters);

        return $semesters
            ->filter(fn (AcademicSessionSemester $semester): bool => $blocked[$semester->id] === [])
            ->pluck('id')
            ->all();
    }

    public function hasLockedPublishedResults(StudentProfile $student): bool
    {
        return $this->latestResultLockAt($student) !== null;
    }

    public function latestResultLockAt(StudentProfile $student): ?CarbonInterface
    {
        $semesters = AcademicSessionSemester::query()
            ->whereIn('id', $student->courseRegistrations()
                ->where('status', CourseRegistration::StatusAssigned)
                ->whereHas('items', fn ($query) => $query
                    ->where(fn ($items) => $items->whereNotNull('grade')->orWhere('missing_script', true))
                    ->whereHas('courseOffering', fn ($offerings) => $offerings->whereNotNull('results_published_at')))
                ->select('academic_session_semester_id'))
            ->get();
        $blocked = $this->blockingDues($student, $semesters);
        $latest = null;

        foreach ($blocked as $dues) {
            foreach ($dues as $due) {
                $startedAt = $due->assignments->first()?->updated_at ?? $due->updated_at;
                if ($latest === null || $startedAt->greaterThan($latest)) {
                    $latest = $startedAt;
                }
            }
        }

        return $latest;
    }
}
