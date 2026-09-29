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
        $passedCourseIds = $studentProfile->courseRegistrations()
            ->where('status', CourseRegistration::StatusApproved)
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
            ->flatMap(fn (CourseRegistration $registration) => $registration->items)
            ->filter(fn (CourseRegistrationItem $item): bool => $item->grade !== null
                && Grade::from($item->grade)->points() > 0
                && $item->courseOffering?->course_id !== null
                && $item->courseOffering->results_published_at !== null)
            ->map(fn (CourseRegistrationItem $item): int => (int) $item->courseOffering->course_id)
            ->unique()
            ->all();

        $availableCourses = CourseOffering::query()
            ->with(['course', 'prerequisiteCourses'])
            ->whereBelongsTo($semester, 'academicSessionSemester')
            ->whereHas('course', fn ($query) => $query
                ->where('student_set_id', $studentProfile->student_set_id))
            ->get()
            ->map(fn (CourseOffering $offering): array => $this->courseOptionPayload($offering, $passedCourseIds))
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

        $duplicateCode = $selected->pluck('code')->duplicates()->first();

        if (is_string($duplicateCode) && $duplicateCode !== '') {
            throw ValidationException::withMessages([
                'items' => "{$duplicateCode} appears more than once. Keep one entry for that course.",
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
        ])->map(fn (array $group): array => [
            'key' => $group[0],
            'title' => $group[1],
            'courses' => $availableCourses->where('categoryValue', $group[0])->values()->all(),
        ])->filter(fn (array $group): bool => $group['courses'] !== [])->values()->all();
    }

    /** @return array<string, mixed> */
    private function courseOptionPayload(CourseOffering $offering, array $passedCourseIds): array
    {
        $category = $offering->is_elective ? 'elective' : 'normal';
        $missingPrerequisites = $offering->prerequisiteCourses
            ->reject(fn ($course): bool => in_array((int) $course->id, $passedCourseIds, true))
            ->pluck('code')->sort()->values()->all();

        return [
            'courseOfferingId' => (int) $offering->id,
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
