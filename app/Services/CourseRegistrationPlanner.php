<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\AcademicYear;
use App\Models\AcademicYearSemester;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\CourseRegistrationItem;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\ResultSheet;
use App\Models\StudentCourseResult;
use App\Models\StudentProfile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CourseRegistrationPlanner
{
    /**
     * @return array{
     *     availableCourses: array<int, array<string, mixed>>,
     *     selectableCourses: array<int, array<string, mixed>>,
     *     courseGroups: array<int, array{key: string, title: string, detail: string, courses: array<int, array<string, mixed>>}>
     * }
     */
    public function plan(
        StudentProfile $studentProfile,
        Department $department,
        AcademicSession $academicSession,
        AcademicYearSemester $academicYearSemester,
    ): array {
        $studentProfile->loadMissing(['entryAcademicSession', 'currentLevel']);
        $department->loadMissing('faculty.school');
        $academicYearSemester->loadMissing(['academicYear', 'semester']);

        $resultHistory = $this->approvedResultHistory($studentProfile);
        $currentSemesterOfferings = $this->courseOfferingsForDepartmentSemester(
            $department,
            $academicSession,
            $academicYearSemester,
        );
        $currentSessionOfferingsByCourse = $this->courseOfferingsForCurrentSession($department, $academicSession)
            ->keyBy(fn (CourseOffering $offering): int => (int) $offering->course_id);
        $currentCourseIds = $currentSemesterOfferings
            ->pluck('course_id')
            ->map(fn (int $id): int => (int) $id)
            ->values();

        $currentOptions = $currentSemesterOfferings
            ->map(fn (CourseOffering $offering): array => $this->courseOptionPayload(
                $department,
                $offering,
                $this->sourceForCurrentOffering($department, $offering, $resultHistory),
                $this->prerequisiteBlockReasons($offering->course, $resultHistory),
                true,
                null,
            ));
        $previousOptions = $this->previousRequirementOptions(
            $studentProfile,
            $department,
            $academicSession,
            $academicYearSemester,
            $resultHistory,
            $currentSessionOfferingsByCourse,
            $currentCourseIds,
        );

        $availableCourses = $currentOptions
            ->merge($previousOptions)
            ->unique(fn (array $course): string => $this->optionKey($course['source'], $course['courseOfferingId']))
            ->sortBy(fn (array $course): string => $this->sourceSortPrefix($course['source']).'-'.$course['code'])
            ->values();

        return [
            'availableCourses' => $availableCourses->all(),
            'selectableCourses' => $availableCourses
                ->filter(fn (array $course): bool => (bool) $course['selectable'])
                ->values()
                ->all(),
            'courseGroups' => $this->courseGroups($availableCourses),
        ];
    }

    /**
     * @param  array<int, array{course_offering_id: int, source: string}>  $items
     * @return array<int, array<string, mixed>>
     */
    public function selectedOptionsFromPayload(array $plan, array $items, bool $isSubmit): array
    {
        $optionsByKey = collect($plan['availableCourses'] ?? [])
            ->keyBy(fn (array $course): string => $this->optionKey($course['source'], $course['courseOfferingId']));
        $selected = collect($items)
            ->map(function (array $item) use ($optionsByKey): array {
                $key = $this->optionKey((string) $item['source'], (int) $item['course_offering_id']);
                $course = $optionsByKey->get($key);

                if (! is_array($course)) {
                    throw ValidationException::withMessages([
                        'items' => 'One of the selected courses is no longer available for this registration.',
                    ]);
                }

                if (! (bool) $course['selectable']) {
                    throw ValidationException::withMessages([
                        'items' => "{$course['code']} is not offered for registration this semester.",
                    ]);
                }

                return $course;
            })
            ->values();

        $duplicateCode = $selected
            ->pluck('code')
            ->duplicates()
            ->first();

        if (is_string($duplicateCode) && $duplicateCode !== '') {
            throw ValidationException::withMessages([
                'items' => "{$duplicateCode} appears more than once. Keep one entry for that course.",
            ]);
        }

        if ($isSubmit) {
            $blockReasons = $selected
                ->flatMap(fn (array $course): array => collect($course['blockReasons'])
                    ->map(fn (string $reason): string => "{$course['code']}: {$reason}")
                    ->all())
                ->values();

            if ($blockReasons->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'items' => $blockReasons->take(3)->implode(' '),
                ]);
            }
        }

        return $selected->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $availableCourses
     */
    private function courseGroups(Collection $availableCourses): array
    {
        return collect([
            [
                'key' => CourseRegistrationItem::SourceCurrentSet,
                'title' => 'Current Set Courses',
                'detail' => 'Courses normally offered to your set in this semester.',
            ],
            [
                'key' => CourseRegistrationItem::SourceElective,
                'title' => 'Elective Courses',
                'detail' => 'Elective courses available for this semester.',
            ],
            [
                'key' => CourseRegistrationItem::SourceCarryover,
                'title' => 'Carryover Courses',
                'detail' => 'Courses where your latest approved grade is F.',
            ],
            [
                'key' => CourseRegistrationItem::SourceOutstandingRequirement,
                'title' => 'Outstanding Previous Requirements',
                'detail' => 'Required previous courses that still need to be satisfied.',
            ],
        ])->map(function (array $group) use ($availableCourses): array {
            return [
                ...$group,
                'courses' => $availableCourses
                    ->where('source', $group['key'])
                    ->values()
                    ->all(),
            ];
        })->filter(fn (array $group): bool => $group['courses'] !== [])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, array{grade: string, course: Course, context: string}>  $resultHistory
     * @param  Collection<int, CourseOffering>  $currentSessionOfferingsByCourse
     * @param  Collection<int, int>  $currentCourseIds
     * @return Collection<int, array<string, mixed>>
     */
    private function previousRequirementOptions(
        StudentProfile $studentProfile,
        Department $department,
        AcademicSession $academicSession,
        AcademicYearSemester $academicYearSemester,
        Collection $resultHistory,
        Collection $currentSessionOfferingsByCourse,
        Collection $currentCourseIds,
    ): Collection {
        $entryAcademicSession = $studentProfile->entryAcademicSession;

        if (! $entryAcademicSession instanceof AcademicSession) {
            return collect();
        }

        return $this->previousCourseOfferingsForStudentSet($department, $entryAcademicSession, $academicYearSemester)
            ->filter(fn (CourseOffering $offering): bool => ! $currentCourseIds->contains((int) $offering->course_id))
            ->keyBy(fn (CourseOffering $offering): int => (int) $offering->course_id)
            ->map(function (CourseOffering $historicalOffering) use ($academicSession, $currentSessionOfferingsByCourse, $department, $resultHistory): ?array {
                $history = $resultHistory->get((int) $historicalOffering->course_id);
                $category = $this->courseCategoryForOffering($department, $historicalOffering);
                $source = null;

                if (is_array($history) && $history['grade'] === 'F') {
                    $source = CourseRegistrationItem::SourceCarryover;
                } elseif (! is_array($history) && $category !== 'Elective Course') {
                    $source = CourseRegistrationItem::SourceOutstandingRequirement;
                }

                if ($source === null) {
                    return null;
                }

                $registrationOffering = $currentSessionOfferingsByCourse->get((int) $historicalOffering->course_id);
                $blockReasons = $registrationOffering instanceof CourseOffering
                    ? $this->prerequisiteBlockReasons($registrationOffering->course, $resultHistory)
                    : ["This course is not offered in {$academicSession->name} Session."];

                return $this->courseOptionPayload(
                    $department,
                    $registrationOffering instanceof CourseOffering ? $registrationOffering : $historicalOffering,
                    $source,
                    $blockReasons,
                    $registrationOffering instanceof CourseOffering,
                    $this->semesterLabel($historicalOffering),
                );
            })
            ->filter()
            ->values();
    }

    /**
     * @return EloquentCollection<int, CourseOffering>
     */
    private function previousCourseOfferingsForStudentSet(
        Department $department,
        AcademicSession $entryAcademicSession,
        AcademicYearSemester $currentAcademicYearSemester,
    ): EloquentCollection {
        $previousAcademicYearSemesters = $this->previousAcademicYearSemesters($currentAcademicYearSemester);
        $offerings = new EloquentCollection;

        foreach ($previousAcademicYearSemesters as $academicYearSemester) {
            $academicSession = $this->academicSessionForStudentSetLevel(
                $department,
                $entryAcademicSession,
                $academicYearSemester->academicYear,
            );

            if (! $academicSession instanceof AcademicSession) {
                continue;
            }

            $offerings = $offerings->merge($this->courseOfferingsForDepartmentSemester($department, $academicSession, $academicYearSemester));
        }

        return $offerings
            ->sortBy(fn (CourseOffering $offering): string => sprintf(
                '%02d-%02d-%s',
                $offering->academicYearSemester->academic_year_id,
                $offering->academicYearSemester->semester->sort_order,
                $offering->course->code,
            ))
            ->values();
    }

    /**
     * @return EloquentCollection<int, AcademicYearSemester>
     */
    private function previousAcademicYearSemesters(AcademicYearSemester $currentAcademicYearSemester): EloquentCollection
    {
        $currentAcademicYearSemester->loadMissing(['academicYear', 'semester']);

        return AcademicYearSemester::query()
            ->with(['academicYear', 'semester'])
            ->get()
            ->filter(fn (AcademicYearSemester $academicYearSemester): bool => (int) $academicYearSemester->academic_year_id < (int) $currentAcademicYearSemester->academic_year_id
                || (
                    (int) $academicYearSemester->academic_year_id === (int) $currentAcademicYearSemester->academic_year_id
                    && (int) $academicYearSemester->semester->sort_order < (int) $currentAcademicYearSemester->semester->sort_order
                ))
            ->sortBy(fn (AcademicYearSemester $academicYearSemester): string => sprintf(
                '%02d-%02d',
                $academicYearSemester->academic_year_id,
                $academicYearSemester->semester->sort_order,
            ))
            ->values();
    }

    private function academicSessionForStudentSetLevel(
        Department $department,
        AcademicSession $entryAcademicSession,
        AcademicYear $level,
    ): ?AcademicSession {
        $department->loadMissing('faculty.school');
        $school = $department->faculty?->school;

        if ($school === null) {
            return null;
        }

        $sessions = $school->academicSessions()
            ->oldest('starts_on')
            ->get()
            ->values();
        $entryIndex = $sessions->search(fn (AcademicSession $academicSession): bool => (int) $academicSession->id === (int) $entryAcademicSession->id);

        if ($entryIndex === false) {
            return null;
        }

        $targetIndex = (int) $entryIndex + max((int) $level->id - 1, 0);
        $academicSession = $sessions->get($targetIndex);

        return $academicSession instanceof AcademicSession ? $academicSession : null;
    }

    /**
     * @return EloquentCollection<int, CourseOffering>
     */
    private function courseOfferingsForDepartmentSemester(
        Department $department,
        AcademicSession $academicSession,
        AcademicYearSemester $academicYearSemester,
    ): EloquentCollection {
        $query = CourseOffering::query()
            ->with(['course.prerequisites', 'academicYearSemester.academicYear', 'academicYearSemester.semester'])
            ->where('academic_session_id', $academicSession->id)
            ->where('academic_year_semester_id', $academicYearSemester->id);

        $this->whereOfferingOwner($query, $department);

        return $query
            ->get()
            ->sortBy(fn (CourseOffering $offering): string => $offering->course->code)
            ->values();
    }

    /**
     * @return EloquentCollection<int, CourseOffering>
     */
    private function courseOfferingsForCurrentSession(Department $department, AcademicSession $academicSession): EloquentCollection
    {
        $query = CourseOffering::query()
            ->with(['course.prerequisites', 'academicYearSemester.academicYear', 'academicYearSemester.semester'])
            ->where('academic_session_id', $academicSession->id);

        $this->whereOfferingOwner($query, $department);

        return $query
            ->get()
            ->sortBy(fn (CourseOffering $offering): string => sprintf(
                '%02d-%02d-%s',
                $offering->academicYearSemester->academic_year_id,
                $offering->academicYearSemester->semester->sort_order,
                $offering->course->code,
            ))
            ->values();
    }

    /**
     * @return Collection<int, array{grade: string, course: Course, context: string}>
     */
    private function approvedResultHistory(StudentProfile $studentProfile): Collection
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
            ->sortBy(function (StudentCourseResult $courseResult): string {
                $sheet = $courseResult->studentSemesterResult->resultSheet;

                return sprintf(
                    '%s-%03d-%03d-%08d',
                    $sheet->academicSession?->starts_on?->toDateString() ?? '',
                    $sheet->academicYearSemester?->academic_year_id ?? 0,
                    $sheet->academicYearSemester?->semester?->sort_order ?? 0,
                    $courseResult->id,
                );
            })
            ->mapWithKeys(function (StudentCourseResult $courseResult): array {
                $sheetCourse = $courseResult->resultSheetCourse;
                $course = $sheetCourse->courseOffering?->course;

                if (! $course instanceof Course) {
                    return [];
                }

                return [
                    (int) $course->id => [
                        'grade' => strtoupper($courseResult->grade),
                        'course' => $course,
                        'context' => $sheetCourse->course_code,
                    ],
                ];
            });
    }

    /**
     * @param  Collection<int, array{grade: string, course: Course, context: string}>  $resultHistory
     * @return array<int, string>
     */
    private function prerequisiteBlockReasons(Course $course, Collection $resultHistory): array
    {
        $course->loadMissing('prerequisites');

        return $course->prerequisites
            ->map(function (Course $prerequisite) use ($resultHistory): ?string {
                $history = $resultHistory->get((int) $prerequisite->id);

                if (! is_array($history)) {
                    return "{$prerequisite->code} has not been passed yet.";
                }

                if ($history['grade'] === 'F') {
                    return "{$prerequisite->code} was last recorded as F.";
                }

                return null;
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, array{grade: string, course: Course, context: string}>  $resultHistory
     */
    private function sourceForCurrentOffering(Department $department, CourseOffering $offering, Collection $resultHistory): string
    {
        $history = $resultHistory->get((int) $offering->course_id);

        if (is_array($history) && $history['grade'] === 'F') {
            return CourseRegistrationItem::SourceCarryover;
        }

        if ($this->courseCategoryForOffering($department, $offering) === 'Elective Course') {
            return CourseRegistrationItem::SourceElective;
        }

        return CourseRegistrationItem::SourceCurrentSet;
    }

    /**
     * @param  array<int, string>  $blockReasons
     * @return array<string, mixed>
     */
    private function courseOptionPayload(
        Department $department,
        CourseOffering $offering,
        string $source,
        array $blockReasons,
        bool $selectable,
        ?string $originSemester,
    ): array {
        $offering->loadMissing(['course', 'academicYearSemester.academicYear', 'academicYearSemester.semester']);

        return [
            'courseOfferingId' => (int) $offering->id,
            'code' => $offering->course->code,
            'title' => $offering->course->title,
            'units' => (int) $offering->course->credit_units,
            'category' => $this->courseCategoryForOffering($department, $offering),
            'source' => $source,
            'sourceLabel' => $this->sourceLabel($source),
            'isCarryover' => $source === CourseRegistrationItem::SourceCarryover,
            'selectable' => $selectable,
            'blockReasons' => array_values($blockReasons),
            'originSemester' => $originSemester,
        ];
    }

    private function courseCategoryForOffering(Department $department, CourseOffering $offering): string
    {
        $isDepartmentOwned = $offering->owner_type === Department::class
            && (int) $offering->owner_id === (int) $department->id;

        if (! $isDepartmentOwned) {
            return 'Faculty Course';
        }

        return $offering->is_mandatory ? 'Departmental Course' : 'Elective Course';
    }

    private function whereOfferingOwner(Builder $query, Department $department): void
    {
        $department->loadMissing('faculty');
        $faculty = $department->faculty;

        $query->where(function (Builder $query) use ($department, $faculty): void {
            $query->where(function (Builder $query) use ($department): void {
                $query
                    ->where('owner_type', Department::class)
                    ->where('owner_id', $department->id);
            });

            if ($faculty instanceof Faculty) {
                $query->orWhere(function (Builder $query) use ($faculty): void {
                    $query
                        ->where('owner_type', Faculty::class)
                        ->where('owner_id', $faculty->id);
                });
            }
        });
    }

    private function sourceLabel(string $source): string
    {
        return match ($source) {
            CourseRegistrationItem::SourceCurrentSet => 'Current Set',
            CourseRegistrationItem::SourceCarryover => 'Carryover',
            CourseRegistrationItem::SourceOutstandingRequirement => 'Outstanding Requirement',
            CourseRegistrationItem::SourceElective => 'Elective',
            default => 'Course',
        };
    }

    private function sourceSortPrefix(string $source): string
    {
        return match ($source) {
            CourseRegistrationItem::SourceCurrentSet => '1',
            CourseRegistrationItem::SourceElective => '2',
            CourseRegistrationItem::SourceCarryover => '3',
            CourseRegistrationItem::SourceOutstandingRequirement => '4',
            default => '9',
        };
    }

    private function optionKey(string $source, int $courseOfferingId): string
    {
        return "{$source}-{$courseOfferingId}";
    }

    private function semesterLabel(CourseOffering $offering): string
    {
        $offering->loadMissing(['academicYearSemester.academicYear', 'academicYearSemester.semester']);

        return "{$offering->academicYearSemester->academicYear->name} {$offering->academicYearSemester->semester->name} Semester";
    }
}
