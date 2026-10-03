<?php

namespace App\Services;

use App\Grade;
use App\Models\AcademicSessionSemester;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\CourseRegistrationItem;
use App\Models\StudentProfile;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CourseRegistrationPlanner
{
    /** @return array{availableCourses: array<int, array<string, mixed>>, courseGroups: array<int, array<string, mixed>>} */
    public function plan(StudentProfile $studentProfile, AcademicSessionSemester $semester): array
    {
        $semester->loadMissing(['academicSession', 'semester']);
        $history = $studentProfile->courseRegistrations()
            ->where('status', CourseRegistration::StatusAssigned)
            ->with([
                'items.courseOffering:id,course_id,results_published_at',
                'academicSessionSemester.academicSession:id,start_year',
                'academicSessionSemester.semester:id,sort_order',
            ])
            ->get()
            ->filter(function (CourseRegistration $registration) use ($semester): bool {
                $prior = $registration->academicSessionSemester;

                return $prior->academicSession->start_year < $semester->academicSession->start_year
                    || ($prior->academic_session_id === $semester->academic_session_id
                        && $prior->semester->sort_order < $semester->semester->sort_order);
            })
            ->sortBy(fn (CourseRegistration $registration): string => sprintf('%04d-%02d',
                $registration->academicSessionSemester->academicSession->start_year,
                $registration->academicSessionSemester->semester->sort_order,
            ))->values();
        $attempts = $history->flatMap(fn (CourseRegistration $registration) => $registration->items)
            ->filter(fn (CourseRegistrationItem $item): bool => $item->courseOffering?->course_id !== null)
            ->groupBy(fn (CourseRegistrationItem $item): int => (int) $item->courseOffering->course_id);
        $passedCourseIds = $history->flatMap(fn (CourseRegistration $registration) => $registration->items)
            ->filter(fn (CourseRegistrationItem $item): bool => $item->grade !== null
                && Grade::from($item->grade)->points() > 0
                && $item->courseOffering?->course_id !== null
                && $item->courseOffering->results_published_at !== null)
            ->map(fn (CourseRegistrationItem $item): int => (int) $item->courseOffering->course_id)
            ->unique()
            ->all();

        $earlierOfferings = CourseOffering::query()
            ->with(['course', 'prerequisiteCourses', 'academicSessionSemester.academicSession', 'academicSessionSemester.semester'])
            ->availableAsPrerequisiteFor($studentProfile->studentSet, $semester)
            ->get()->sortByDesc(fn (CourseOffering $offering): string => sprintf('%04d-%02d',
                $offering->academicSessionSemester->academicSession->start_year,
                $offering->academicSessionSemester->semester->sort_order,
            ))->unique('course_id')->keyBy('course_id');
        $outstanding = $earlierOfferings->mapWithKeys(function (CourseOffering $offering) use ($attempts, $passedCourseIds): array {
            $courseId = (int) $offering->course_id;
            if (in_array($courseId, $passedCourseIds, true)) {
                return [];
            }

            $lastAttempt = $attempts->get($courseId)?->last();
            if ($lastAttempt instanceof CourseRegistrationItem) {
                if ($lastAttempt->courseOffering?->results_published_at === null || $lastAttempt->grade !== 'F') {
                    return [];
                }

                return [$courseId => 'carryover'];
            }

            return [$courseId => 'deferred'];
        });
        $currentOfferings = CourseOffering::query()
            ->with(['course', 'prerequisiteCourses'])
            ->whereBelongsTo($semester, 'academicSessionSemester')
            ->whereHas('course', fn ($query) => $query
                ->where('student_set_id', $studentProfile->student_set_id))
            ->get();
        $availableCourses = $currentOfferings
            ->filter(fn (CourseOffering $offering): bool => ! in_array((int) $offering->course_id, $passedCourseIds, true)
                && (! $offering->is_retake_only || $outstanding->has((int) $offering->course_id)))
            ->map(fn (CourseOffering $offering): array => $this->courseOptionPayload(
                $offering, $passedCourseIds, $outstanding->get((int) $offering->course_id, 'scheduled'),
            ))
            ->concat($earlierOfferings
                ->filter(fn (CourseOffering $offering): bool => $outstanding->has((int) $offering->course_id)
                    && ! $currentOfferings->contains('course_id', $offering->course_id))
                ->map(fn (CourseOffering $offering): array => $this->courseOptionPayload(
                    $offering, $passedCourseIds, $outstanding->get((int) $offering->course_id),
                )))
            ->sortBy(fn (array $course): string => $course['code'])
            ->values();

        return [
            'availableCourses' => $availableCourses->all(),
            'courseGroups' => $this->courseGroups($availableCourses),
        ];
    }

    /** @param array<int, array{course_offering_id: int}> $items
     * @return array<int, array<string, mixed>>
     */
    public function selectedOptionsFromPayload(array $plan, array $items, int $maximumUnits): array
    {
        $options = collect($plan['availableCourses'] ?? [])->keyBy('courseOfferingId');
        $selected = collect($items)->map(function (array $item) use ($options): array {
            $course = $options->get((int) $item['course_offering_id']);

            if (! is_array($course)) {
                throw ValidationException::withMessages([
                    'items' => 'One selected course is no longer available for this registration.',
                ]);
            }

            return $course;
        })->values();

        $duplicateCode = $selected->pluck('courseId')->duplicates()->first();

        if ($duplicateCode !== null) {
            throw ValidationException::withMessages([
                'items' => 'The same course appears more than once. Keep one entry for that course.',
            ]);
        }

        $ineligible = $selected->first(fn (array $course): bool => $course['missingPrerequisites'] !== []);

        if (is_array($ineligible)) {
            throw ValidationException::withMessages([
                'items' => "{$ineligible['code']} requires a passing published result in ".implode(', ', $ineligible['missingPrerequisites']).'.',
            ]);
        }

        if ((int) $selected->sum('units') > $maximumUnits) {
            throw ValidationException::withMessages([
                'items' => "This semester allows a maximum of {$maximumUnits} course units.",
            ]);
        }

        return $selected->all();
    }

    /** @param Collection<int, array<string, mixed>> $availableCourses */
    private function courseGroups(Collection $availableCourses): array
    {
        return collect([
            ['normal', 'Normal Courses'],
            ['elective', 'Elective Courses'],
            ['carryover', 'Carryover Courses'],
            ['deferred', 'Deferred Courses'],
        ])->map(fn (array $group): array => [
            'key' => $group[0],
            'title' => $group[1],
            'courses' => $availableCourses->where('categoryValue', $group[0])->values()->all(),
        ])->filter(fn (array $group): bool => $group['courses'] !== [])->values()->all();
    }

    /** @return array<string, mixed> */
    private function courseOptionPayload(CourseOffering $offering, array $passedCourseIds, string $origin): array
    {
        $category = $origin === 'scheduled' ? ($offering->is_elective ? 'elective' : 'normal') : $origin;
        $missingPrerequisites = $offering->prerequisiteCourses
            ->reject(fn ($course): bool => in_array((int) $course->id, $passedCourseIds, true))
            ->pluck('code')->sort()->values()->all();

        return [
            'courseOfferingId' => (int) $offering->id,
            'courseId' => (int) $offering->course_id,
            'assignmentOrigin' => $origin,
            'code' => $offering->course->code,
            'title' => $offering->course_title,
            'units' => (int) $offering->credit_units,
            'category' => $offering->is_elective ? 'Elective Course' : 'Normal Course',
            'categoryValue' => $category,
            'isElective' => (bool) $offering->is_elective,
            'prerequisites' => $offering->prerequisiteCourses->pluck('code')->values()->all(),
            'missingPrerequisites' => $missingPrerequisites,
        ];
    }
}
