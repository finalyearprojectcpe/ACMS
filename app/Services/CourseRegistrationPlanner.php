<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\AcademicYearSemester;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\CourseRegistrationItem;
use App\Models\StudentCourseResult;
use App\Models\StudentProfile;
use App\Models\StudentSet;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CourseRegistrationPlanner
{
    public function __construct(
        private readonly AcademicContextService $academicContext,
        private readonly AcademicPerformanceService $academicPerformance,
    ) {}

    /** @return array{availableCourses: array<int, array<string, mixed>>, selectableCourses: array<int, array<string, mixed>>, courseGroups: array<int, array<string, mixed>>} */
    public function plan(StudentProfile $studentProfile, AcademicSession $academicSession, AcademicYearSemester $academicYearSemester): array
    {
        $studentProfile->loadMissing('studentSet.entryAcademicSession');
        $studentSet = $studentProfile->studentSet;
        $academicYearSemester->loadMissing(['academicYear', 'semester']);

        $resultHistory = $this->publishedResultHistory($studentProfile);
        $currentOfferings = $this->offeringsForContext($studentSet, $academicSession, $academicYearSemester);
        $sameSemesterOfferings = $this->offeringsForSessionSemester($studentSet, $academicSession, (int) $academicYearSemester->semester_id)
            ->keyBy(fn (CourseOffering $offering): int => (int) $offering->course_id);
        $currentCourseIds = $currentOfferings->pluck('course_id')->map(fn (int $id): int => $id)->values();

        $currentOptions = $currentOfferings->toBase()->map(fn (CourseOffering $offering): array => $this->courseOptionPayload(
            $offering,
            $this->sourceForCurrentOffering($offering, $resultHistory),
            $this->prerequisiteBlockReasons($offering, $resultHistory),
            true,
            null,
        ));
        $previousOptions = $this->previousRequirementOptions(
            $studentSet,
            $academicSession,
            $academicYearSemester,
            $resultHistory,
            $sameSemesterOfferings,
            $currentCourseIds,
        );
        $availableCourses = $currentOptions
            ->merge($previousOptions)
            ->unique(fn (array $course): string => $this->optionKey($course['source'], $course['courseOfferingId']))
            ->sortBy(fn (array $course): string => $this->sourceSortPrefix($course['source']).'-'.$course['code'])
            ->values();

        return [
            'availableCourses' => $availableCourses->all(),
            'selectableCourses' => $availableCourses->where('selectable', true)->values()->all(),
            'courseGroups' => $this->courseGroups($availableCourses),
        ];
    }

    /** @param array<int, array{course_offering_id: int, source: string}> $items
     * @return array<int, array<string, mixed>>
     */
    public function selectedOptionsFromPayload(array $plan, array $items, bool $isSubmit): array
    {
        $options = collect($plan['availableCourses'] ?? [])->keyBy(
            fn (array $course): string => $this->optionKey($course['source'], $course['courseOfferingId']),
        );
        $selected = collect($items)->map(function (array $item) use ($options): array {
            $course = $options->get($this->optionKey((string) $item['source'], (int) $item['course_offering_id']));

            if (! is_array($course) || ! $course['selectable']) {
                throw ValidationException::withMessages(['items' => 'One selected course is no longer available for this registration.']);
            }

            return $course;
        })->values();

        $duplicateCode = $selected->pluck('code')->duplicates()->first();

        if (is_string($duplicateCode) && $duplicateCode !== '') {
            throw ValidationException::withMessages(['items' => "{$duplicateCode} appears more than once. Keep one entry for that course."]);
        }

        if ($isSubmit) {
            $reasons = $selected->flatMap(fn (array $course): array => collect($course['blockReasons'])
                ->map(fn (string $reason): string => "{$course['code']}: {$reason}")
                ->all());

            if ($reasons->isNotEmpty()) {
                throw ValidationException::withMessages(['items' => $reasons->take(3)->implode(' ')]);
            }
        }

        return $selected->all();
    }

    /** @param Collection<int, array<string, mixed>> $availableCourses */
    private function courseGroups(Collection $availableCourses): array
    {
        return collect([
            [CourseRegistrationItem::SourceCurrentSet, 'Current Set Courses', 'Courses offered to your set in this semester.'],
            [CourseRegistrationItem::SourceElective, 'Elective Courses', 'Elective courses available in this semester.'],
            [CourseRegistrationItem::SourceCarryover, 'Carryover Courses', 'Courses where your latest published grade is F.'],
            [CourseRegistrationItem::SourceOutstandingRequirement, 'Outstanding Requirements', 'Previous required courses you have not completed.'],
        ])->map(fn (array $group): array => [
            'key' => $group[0],
            'title' => $group[1],
            'detail' => $group[2],
            'courses' => $availableCourses->where('source', $group[0])->values()->all(),
        ])->filter(fn (array $group): bool => $group['courses'] !== [])->values()->all();
    }

    /** @param Collection<int, array{grade: string, course: Course, context: string}> $resultHistory
     * @param Collection<int, CourseOffering> $sameSemesterOfferings
     * @param Collection<int, int> $currentCourseIds
     * @return Collection<int, array<string, mixed>>
     */
    private function previousRequirementOptions(StudentSet $studentSet, AcademicSession $academicSession, AcademicYearSemester $currentSlot, Collection $resultHistory, Collection $sameSemesterOfferings, Collection $currentCourseIds): Collection
    {
        return $this->previousOfferings($studentSet, $currentSlot)
            ->toBase()
            ->filter(fn (CourseOffering $offering): bool => ! $currentCourseIds->contains((int) $offering->course_id))
            ->keyBy(fn (CourseOffering $offering): int => (int) $offering->course_id)
            ->map(function (CourseOffering $historicalOffering) use ($academicSession, $sameSemesterOfferings, $resultHistory): ?array {
                $history = $resultHistory->get((int) $historicalOffering->course_id);
                $source = is_array($history) && $history['grade'] === 'F'
                    ? CourseRegistrationItem::SourceCarryover
                    : (! is_array($history) && $historicalOffering->category->value !== 'elective'
                        ? CourseRegistrationItem::SourceOutstandingRequirement
                        : null);

                if ($source === null) {
                    return null;
                }

                $offering = $sameSemesterOfferings->get((int) $historicalOffering->course_id);
                $selectable = $offering instanceof CourseOffering;

                return $this->courseOptionPayload(
                    $selectable ? $offering : $historicalOffering,
                    $source,
                    $selectable ? $this->prerequisiteBlockReasons($offering, $resultHistory) : ["This course is not offered in {$academicSession->name} Session."],
                    $selectable,
                    $this->semesterLabel($historicalOffering),
                );
            })->filter()->values();
    }

    /** @return EloquentCollection<int, CourseOffering> */
    private function previousOfferings(StudentSet $studentSet, AcademicYearSemester $currentSlot): EloquentCollection
    {
        $offerings = new EloquentCollection;

        foreach ($this->previousSlots($currentSlot) as $slot) {
            $session = $this->academicContext->sessionForSetYear($studentSet, $slot->academicYear);

            if ($session instanceof AcademicSession) {
                $offerings = $offerings->merge($this->offeringsForContext($studentSet, $session, $slot));
            }
        }

        return $offerings->sortBy(fn (CourseOffering $offering): string => sprintf(
            '%02d-%02d-%s',
            $offering->academicYearSemester->academicYear->year_number,
            $offering->academicYearSemester->semester->sort_order,
            $offering->course->code,
        ))->values();
    }

    /** @return EloquentCollection<int, AcademicYearSemester> */
    private function previousSlots(AcademicYearSemester $currentSlot): EloquentCollection
    {
        $currentSlot->loadMissing(['academicYear', 'semester']);

        return AcademicYearSemester::query()->with(['academicYear', 'semester'])->get()
            ->filter(fn (AcademicYearSemester $slot): bool => $slot->academicYear->year_number < $currentSlot->academicYear->year_number
                || ($slot->academicYear->year_number === $currentSlot->academicYear->year_number && $slot->semester->sort_order < $currentSlot->semester->sort_order))
            ->sortBy(fn (AcademicYearSemester $slot): string => sprintf('%02d-%02d', $slot->academicYear->year_number, $slot->semester->sort_order))
            ->values();
    }

    /** @return EloquentCollection<int, CourseOffering> */
    private function offeringsForContext(StudentSet $studentSet, AcademicSession $session, AcademicYearSemester $slot): EloquentCollection
    {
        return CourseOffering::query()
            ->with(['course', 'prerequisiteCourses', 'academicYearSemester.academicYear', 'academicYearSemester.semester'])
            ->whereBelongsTo($studentSet)
            ->whereBelongsTo($session, 'academicSession')
            ->whereBelongsTo($slot, 'academicYearSemester')
            ->whereNull('archived_at')
            ->get()
            ->sortBy(fn (CourseOffering $offering): string => $offering->course->code)
            ->values();
    }

    /** @return EloquentCollection<int, CourseOffering> */
    private function offeringsForSessionSemester(StudentSet $studentSet, AcademicSession $session, int $semesterId): EloquentCollection
    {
        return CourseOffering::query()
            ->with(['course', 'prerequisiteCourses', 'academicYearSemester.academicYear', 'academicYearSemester.semester'])
            ->whereBelongsTo($studentSet)
            ->whereBelongsTo($session, 'academicSession')
            ->whereHas('academicYearSemester', fn (Builder $query): Builder => $query->where('semester_id', $semesterId))
            ->whereNull('archived_at')
            ->get()
            ->sortBy(fn (CourseOffering $offering): string => sprintf('%02d-%s', $offering->academicYearSemester->academicYear->year_number, $offering->course->code))
            ->values();
    }

    /** @return Collection<int, array{grade: string, course: Course, context: string}> */
    private function publishedResultHistory(StudentProfile $studentProfile): Collection
    {
        return $this->academicPerformance->latestPublishedCourseAttempts($studentProfile)
            ->mapWithKeys(function (StudentCourseResult $result): array {
                $course = $result->resultSheetCourse->courseOffering?->course;

                return $course instanceof Course ? [(int) $course->id => [
                    'grade' => strtoupper($result->grade),
                    'course' => $course,
                    'context' => $result->resultSheetCourse->course_code,
                ]] : [];
            });
    }

    /** @param Collection<int, array{grade: string, course: Course, context: string}> $resultHistory
     * @return array<int, string>
     */
    private function prerequisiteBlockReasons(CourseOffering $offering, Collection $resultHistory): array
    {
        $offering->loadMissing('prerequisiteCourses');

        return $offering->prerequisiteCourses->map(function (Course $course) use ($resultHistory): ?string {
            $history = $resultHistory->get((int) $course->id);

            if (! is_array($history)) {
                return "{$course->code} has not been passed yet.";
            }

            return $history['grade'] === 'F' ? "{$course->code} was last recorded as F." : null;
        })->filter()->values()->all();
    }

    /** @param Collection<int, array{grade: string, course: Course, context: string}> $resultHistory */
    private function sourceForCurrentOffering(CourseOffering $offering, Collection $resultHistory): string
    {
        $history = $resultHistory->get((int) $offering->course_id);

        if (is_array($history) && $history['grade'] === 'F') {
            return CourseRegistrationItem::SourceCarryover;
        }

        return $offering->category->value === 'elective'
            ? CourseRegistrationItem::SourceElective
            : CourseRegistrationItem::SourceCurrentSet;
    }

    /** @param array<int, string> $blockReasons
     * @return array<string, mixed>
     */
    private function courseOptionPayload(CourseOffering $offering, string $source, array $blockReasons, bool $selectable, ?string $originSemester): array
    {
        $offering->loadMissing(['course', 'academicYearSemester.academicYear', 'academicYearSemester.semester']);

        return [
            'courseOfferingId' => (int) $offering->id,
            'code' => $offering->course->code,
            'title' => $offering->course_title,
            'units' => (int) $offering->credit_units,
            'category' => $offering->category->label(),
            'source' => $source,
            'sourceLabel' => $this->sourceLabel($source),
            'isCarryover' => $source === CourseRegistrationItem::SourceCarryover,
            'selectable' => $selectable,
            'blockReasons' => array_values($blockReasons),
            'originSemester' => $originSemester,
        ];
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

    private function optionKey(string $source, int $offeringId): string
    {
        return "{$source}-{$offeringId}";
    }

    private function semesterLabel(CourseOffering $offering): string
    {
        return "{$offering->academicYearSemester->academicYear->name} {$offering->academicYearSemester->semester->name} Semester";
    }
}
