<?php

namespace App\Services;

use App\CourseCategory;
use App\Models\AcademicSession;
use App\Models\AcademicYearSemester;
use App\Models\CourseOffering;
use App\Models\StudentProfile;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CourseRegistrationPlanner
{
    /** @return array{availableCourses: array<int, array<string, mixed>>, selectableCourses: array<int, array<string, mixed>>, courseGroups: array<int, array<string, mixed>>} */
    public function plan(StudentProfile $studentProfile, AcademicSession $academicSession, AcademicYearSemester $academicYearSemester): array
    {
        $studentProfile->loadMissing('studentSet.entryAcademicSession');
        $studentSet = $studentProfile->studentSet;
        $academicYearSemester->loadMissing(['academicYear', 'semester']);

        $availableCourses = CourseOffering::query()
            ->with(['course', 'prerequisiteCourses'])
            ->whereBelongsTo($studentSet)
            ->whereBelongsTo($academicSession, 'academicSession')
            ->whereBelongsTo($academicYearSemester, 'academicYearSemester')
            ->whereNull('archived_at')
            ->get()
            ->map(fn (CourseOffering $offering): array => $this->courseOptionPayload($offering))
            ->sortBy(fn (array $course): string => $course['code'])
            ->values();

        return [
            'availableCourses' => $availableCourses->all(),
            'selectableCourses' => $availableCourses->all(),
            'courseGroups' => $this->courseGroups($availableCourses),
        ];
    }

    /** @param array<int, array{course_offering_id: int}> $items
     * @return array<int, array<string, mixed>>
     */
    public function selectedOptionsFromPayload(array $plan, array $items): array
    {
        $options = collect($plan['availableCourses'] ?? [])->keyBy('courseOfferingId');
        $selected = collect($items)->map(function (array $item) use ($options): array {
            $course = $options->get((int) $item['course_offering_id']);

            if (! is_array($course)) {
                throw ValidationException::withMessages(['items' => 'One selected course is no longer available for this registration.']);
            }

            return $course;
        })->values();

        $duplicateCode = $selected->pluck('code')->duplicates()->first();

        if (is_string($duplicateCode) && $duplicateCode !== '') {
            throw ValidationException::withMessages(['items' => "{$duplicateCode} appears more than once. Keep one entry for that course."]);
        }

        return $selected->all();
    }

    /** @param Collection<int, array<string, mixed>> $availableCourses */
    private function courseGroups(Collection $availableCourses): array
    {
        return collect([
            [CourseCategory::Normal->value, 'Normal Courses'],
            [CourseCategory::Elective->value, 'Elective Courses'],
        ])->map(fn (array $group): array => [
            'key' => $group[0],
            'title' => $group[1],
            'courses' => $availableCourses->where('categoryValue', $group[0])->values()->all(),
        ])->filter(fn (array $group): bool => $group['courses'] !== [])->values()->all();
    }

    /** @return array<string, mixed> */
    private function courseOptionPayload(CourseOffering $offering): array
    {
        $offering->loadMissing(['course', 'prerequisiteCourses']);

        return [
            'courseOfferingId' => (int) $offering->id,
            'code' => $offering->course->code,
            'title' => $offering->course_title,
            'units' => (int) $offering->credit_units,
            'category' => $offering->category->label(),
            'categoryValue' => $offering->category->value,
            'isElective' => $offering->category === CourseCategory::Elective,
            'prerequisites' => $offering->prerequisiteCourses
                ->map(fn ($course): string => $course->code)
                ->values()
                ->all(),
        ];
    }
}
