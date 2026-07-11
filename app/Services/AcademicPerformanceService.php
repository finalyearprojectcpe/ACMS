<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\ResultSheet;
use App\Models\StudentCourseResult;
use App\Models\StudentProfile;
use App\Models\StudentSemesterResult;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AcademicPerformanceService
{
    /**
     * @return array{cgpa: string|null, totalUnits: int, totalQualityPoints: int, semesterCount: int}
     */
    public function summary(StudentProfile $studentProfile): array
    {
        $results = $this->approvedSemesterResults($studentProfile);
        $totalUnits = (int) $results->sum('total_units');
        $totalQualityPoints = (int) $results->sum('total_grade_points');

        return [
            'cgpa' => $totalUnits > 0 ? number_format($totalQualityPoints / $totalUnits, 2, '.', '') : null,
            'totalUnits' => $totalUnits,
            'totalQualityPoints' => $totalQualityPoints,
            'semesterCount' => $results->count(),
        ];
    }

    /**
     * @param  Collection<int, StudentProfile>  $studentProfiles
     * @return Collection<int, array{cgpa: string|null, totalUnits: int, totalQualityPoints: int, semesterCount: int}>
     */
    public function summariesFor(Collection $studentProfiles): Collection
    {
        if ($studentProfiles->isEmpty()) {
            return collect();
        }

        $results = StudentSemesterResult::query()
            ->whereIn('student_profile_id', $studentProfiles->pluck('id'))
            ->whereHas('resultSheet', fn (Builder $query) => $query->where('status', ResultSheet::StatusApproved))
            ->get()
            ->groupBy('student_profile_id');

        return $studentProfiles->mapWithKeys(function (StudentProfile $studentProfile) use ($results): array {
            $studentResults = $results->get($studentProfile->id, collect());
            $totalUnits = (int) $studentResults->sum('total_units');
            $totalQualityPoints = (int) $studentResults->sum('total_grade_points');

            return [
                $studentProfile->id => [
                    'cgpa' => $totalUnits > 0 ? number_format($totalQualityPoints / $totalUnits, 2, '.', '') : null,
                    'totalUnits' => $totalUnits,
                    'totalQualityPoints' => $totalQualityPoints,
                    'semesterCount' => $studentResults->count(),
                ],
            ];
        });
    }

    /**
     * @return Collection<int, array{level: AcademicYear, gpa: string|null, totalUnits: int, totalQualityPoints: int}>
     */
    public function gpaByAcademicYear(
        StudentProfile $studentProfile,
        ?int $maximumYearNumber = null,
    ): Collection {
        $grouped = $this->approvedSemesterResults($studentProfile)
            ->filter(fn (StudentSemesterResult $result): bool => $result->resultSheet?->academicYearSemester?->academicYear instanceof AcademicYear)
            ->groupBy(fn (StudentSemesterResult $result): int => (int) $result->resultSheet->academicYearSemester->academicYear->year_number);

        return AcademicYear::query()
            ->when(
                $maximumYearNumber !== null,
                fn (Builder $query): Builder => $query->where('year_number', '<=', $maximumYearNumber),
            )
            ->orderBy('year_number')
            ->get()
            ->map(function (AcademicYear $academicYear) use ($grouped): array {
                $results = $grouped->get($academicYear->year_number, collect());
                $totalUnits = (int) $results->sum('total_units');
                $totalQualityPoints = (int) $results->sum('total_grade_points');

                return [
                    'level' => $academicYear,
                    'gpa' => $totalUnits > 0 ? number_format($totalQualityPoints / $totalUnits, 2, '.', '') : null,
                    'totalUnits' => $totalUnits,
                    'totalQualityPoints' => $totalQualityPoints,
                ];
            });
    }

    /**
     * Builds table-ready academic summaries without issuing a separate result query per student.
     *
     * @param  Collection<int, StudentProfile>  $studentProfiles
     * @return Collection<int, array{cgpa: string|null, levelGpas: array<int, array{level: AcademicYear, gpa: string|null}>, pendingCarryovers: array<int, string>}>
     */
    public function tableSummariesFor(Collection $studentProfiles, int $maximumYearNumber): Collection
    {
        if ($studentProfiles->isEmpty()) {
            return collect();
        }

        $studentProfileIds = $studentProfiles->pluck('id');
        $academicYears = AcademicYear::query()
            ->where('year_number', '<=', $maximumYearNumber)
            ->orderBy('year_number')
            ->get();
        $semesterResults = StudentSemesterResult::query()
            ->with('resultSheet.academicYearSemester.academicYear')
            ->whereIn('student_profile_id', $studentProfileIds)
            ->whereHas('resultSheet', fn (Builder $query) => $query->where('status', ResultSheet::StatusApproved))
            ->get()
            ->groupBy('student_profile_id');
        $courseResults = StudentCourseResult::query()
            ->with([
                'resultSheetCourse.courseOffering.course',
                'studentSemesterResult.resultSheet.academicSession',
                'studentSemesterResult.resultSheet.academicYearSemester.academicYear',
                'studentSemesterResult.resultSheet.academicYearSemester.semester',
            ])
            ->whereHas('studentSemesterResult', fn (Builder $query) => $query
                ->whereIn('student_profile_id', $studentProfileIds)
                ->whereHas('resultSheet', fn (Builder $sheetQuery) => $sheetQuery->where('status', ResultSheet::StatusApproved)))
            ->get()
            ->sortBy(fn (StudentCourseResult $result): string => $this->attemptOrder($result));
        $latestAttempts = [];

        foreach ($courseResults as $courseResult) {
            $studentProfileId = (int) $courseResult->studentSemesterResult?->student_profile_id;
            $courseId = (int) $courseResult->resultSheetCourse?->courseOffering?->course_id;

            if ($studentProfileId > 0 && $courseId > 0) {
                $latestAttempts[$studentProfileId][$courseId] = $courseResult;
            }
        }

        return $studentProfiles->mapWithKeys(function (StudentProfile $studentProfile) use ($academicYears, $latestAttempts, $maximumYearNumber, $semesterResults): array {
            /** @var Collection<int, StudentSemesterResult> $results */
            $results = $semesterResults->get($studentProfile->id, collect());
            $totalUnits = (int) $results->sum('total_units');
            $totalQualityPoints = (int) $results->sum('total_grade_points');
            $resultsByYear = $results
                ->filter(fn (StudentSemesterResult $result): bool => $result->resultSheet?->academicYearSemester?->academicYear instanceof AcademicYear)
                ->groupBy(fn (StudentSemesterResult $result): int => (int) $result->resultSheet->academicYearSemester->academicYear->year_number);
            $studentMaximumYearNumber = min(
                $maximumYearNumber,
                $studentProfile->currentLevel?->year_number ?? $maximumYearNumber,
            );
            $levelGpas = $academicYears
                ->filter(fn (AcademicYear $academicYear): bool => $academicYear->year_number <= $studentMaximumYearNumber)
                ->map(function (AcademicYear $academicYear) use ($resultsByYear): array {
                    /** @var Collection<int, StudentSemesterResult> $yearResults */
                    $yearResults = $resultsByYear->get($academicYear->year_number, collect());
                    $yearUnits = (int) $yearResults->sum('total_units');
                    $yearQualityPoints = (int) $yearResults->sum('total_grade_points');

                    return [
                        'level' => $academicYear,
                        'gpa' => $yearUnits > 0
                            ? number_format($yearQualityPoints / $yearUnits, 2, '.', '')
                            : null,
                    ];
                })
                ->all();
            $pendingCarryovers = collect($latestAttempts[$studentProfile->id] ?? [])
                ->filter(fn (StudentCourseResult $result): bool => strtoupper($result->grade) === 'F')
                ->map(fn (StudentCourseResult $result): string => $result->resultSheetCourse?->course_code ?? '')
                ->filter()
                ->sort()
                ->values()
                ->all();

            return [
                (int) $studentProfile->id => [
                    'cgpa' => $totalUnits > 0
                        ? number_format($totalQualityPoints / $totalUnits, 2, '.', '')
                        : null,
                    'levelGpas' => $levelGpas,
                    'pendingCarryovers' => $pendingCarryovers,
                ],
            ];
        });
    }

    /**
     * Returns one row per course only when the student's latest approved attempt is F.
     *
     * @return Collection<int, array{course: Course, code: string, title: string, units: int, originalSemester: string}>
     */
    public function carryovers(StudentProfile $studentProfile): Collection
    {
        return $this->latestApprovedCourseAttempts($studentProfile)
            ->filter(fn (StudentCourseResult $result): bool => strtoupper($result->grade) === 'F')
            ->map(function (StudentCourseResult $result): ?array {
                $sheetCourse = $result->resultSheetCourse;
                $course = $sheetCourse?->courseOffering?->course;
                $sheet = $result->studentSemesterResult?->resultSheet;

                if (! $course instanceof Course || $sheet === null) {
                    return null;
                }

                return [
                    'course' => $course,
                    'code' => $sheetCourse->course_code,
                    'title' => $sheetCourse->course_title,
                    'units' => (int) $sheetCourse->credit_units,
                    'originalSemester' => trim(sprintf(
                        '%s %s Semester',
                        $sheet->academicYearSemester?->academicYear?->name ?? '',
                        $sheet->academicYearSemester?->semester?->name ?? '',
                    )),
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * @return Collection<int, StudentCourseResult>
     */
    public function latestApprovedCourseAttempts(StudentProfile $studentProfile): Collection
    {
        return StudentCourseResult::query()
            ->with([
                'resultSheetCourse.courseOffering.course',
                'studentSemesterResult.resultSheet.academicSession',
                'studentSemesterResult.resultSheet.academicYearSemester.academicYear',
                'studentSemesterResult.resultSheet.academicYearSemester.semester',
            ])
            ->whereHas('studentSemesterResult', fn (Builder $query) => $query
                ->where('student_profile_id', $studentProfile->id)
                ->whereHas('resultSheet', fn (Builder $query) => $query->where('status', ResultSheet::StatusApproved)))
            ->get()
            ->sortBy(fn (StudentCourseResult $result): string => $this->attemptOrder($result))
            ->mapWithKeys(function (StudentCourseResult $result): array {
                $course = $result->resultSheetCourse?->courseOffering?->course;

                return $course instanceof Course ? [(int) $course->id => $result] : [];
            });
    }

    /**
     * @return Collection<int, StudentSemesterResult>
     */
    public function approvedSemesterResults(StudentProfile $studentProfile): Collection
    {
        return StudentSemesterResult::query()
            ->with([
                'resultSheet.academicSession',
                'resultSheet.academicYearSemester.academicYear',
                'resultSheet.academicYearSemester.semester',
                'courseResults.resultSheetCourse.courseOffering.course',
            ])
            ->where('student_profile_id', $studentProfile->id)
            ->whereHas('resultSheet', fn (Builder $query) => $query->where('status', ResultSheet::StatusApproved))
            ->get()
            ->sortBy(fn (StudentSemesterResult $result): string => sprintf(
                '%s-%03d-%03d',
                $result->resultSheet?->academicSession?->starts_on?->toDateString() ?? '',
                $result->resultSheet?->academicYearSemester?->academicYear?->year_number ?? 0,
                $result->resultSheet?->academicYearSemester?->semester?->sort_order ?? 0,
            ))
            ->values();
    }

    private function attemptOrder(StudentCourseResult $result): string
    {
        $sheet = $result->studentSemesterResult?->resultSheet;

        return sprintf(
            '%s-%03d-%03d-%010d',
            $sheet?->academicSession?->starts_on?->toDateString() ?? '',
            $sheet?->academicYearSemester?->academicYear?->year_number ?? 0,
            $sheet?->academicYearSemester?->semester?->sort_order ?? 0,
            $result->id,
        );
    }
}
