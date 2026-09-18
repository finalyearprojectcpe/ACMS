<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdviserCourseOfferingRequest;
use App\Models\AcademicSession;
use App\Models\AcademicYearSemester;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\StudentSet;
use App\Services\AcademicContextService;
use App\Services\DashboardAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CourseOfferingController extends Controller
{
    public function __construct(
        private readonly DashboardAccess $access,
        private readonly AcademicContextService $academicContext,
    ) {}

    public function store(
        AdviserCourseOfferingRequest $request,
        string $level,
        string $semester,
    ): RedirectResponse {
        $advisor = $this->access->advisor($request);
        $set = $this->access->selectedStudentSet($request, $advisor);
        $context = $this->academicContext->courseOfferingContext($set, $level, $semester);
        $year = $context['year'];
        $semesterModel = $context['semester'];
        $session = $context['session'];
        $slot = $context['slot'];
        $validated = $request->validated();
        $course = Course::query()->updateOrCreate(
            ['advisor_profile_id' => $advisor->id, 'code' => $validated['code']],
            [
                'title' => $validated['title'],
                'slug' => Str::slug($validated['code'].' '.$validated['title']),
                'credit_units' => (int) $validated['credit_units'],
            ],
        );
        $offering = CourseOffering::query()->updateOrCreate([
            'student_set_id' => $set->id,
            'course_id' => $course->id,
            'academic_session_id' => $session->id,
            'academic_year_semester_id' => $slot->id,
        ], [
            'course_title' => $validated['title'],
            'credit_units' => (int) $validated['credit_units'],
            'category' => $validated['category'],
            'archived_at' => null,
        ]);
        $offering->prerequisiteCourses()->sync($this->validPrerequisiteIds(
            $set,
            $session,
            $slot,
            $validated['prerequisite_course_ids'] ?? [],
        ));

        return to_route('dashboard.section', [
            'section' => 'courses',
            'set' => $set->id,
            'level' => $year->slug,
            'semester' => $semesterModel->slug,
        ])->with('success', 'Course offering saved.');
    }

    public function update(
        AdviserCourseOfferingRequest $request,
        string $level,
        string $semester,
        CourseOffering $offering,
    ): RedirectResponse {
        $advisor = $this->access->advisor($request);
        $set = $this->access->studentSet($advisor, (int) $offering->student_set_id);
        $context = $this->academicContext->courseOfferingContext($set, $level, $semester);
        $year = $context['year'];
        $semesterModel = $context['semester'];
        $session = $context['session'];
        $slot = $context['slot'];
        $this->ensureOfferingContext($offering, $set, $session, $slot);
        $validated = $request->validated();
        $offering->course->forceFill([
            'code' => $validated['code'],
            'title' => $validated['title'],
            'slug' => Str::slug($validated['code'].' '.$validated['title']),
            'credit_units' => (int) $validated['credit_units'],
        ])->save();
        $offering->forceFill([
            'course_title' => $validated['title'],
            'credit_units' => (int) $validated['credit_units'],
            'category' => $validated['category'],
        ])->save();
        $offering->prerequisiteCourses()->sync($this->validPrerequisiteIds(
            $set,
            $session,
            $slot,
            $validated['prerequisite_course_ids'] ?? [],
            (int) $offering->course_id,
        ));

        return to_route('dashboard.section', [
            'section' => 'courses',
            'set' => $set->id,
            'level' => $year->slug,
            'semester' => $semesterModel->slug,
        ])->with('success', 'Course offering updated.');
    }

    /** @param array<int, int|string> $ids
     * @return array<int, int>
     */
    private function validPrerequisiteIds(
        StudentSet $set,
        AcademicSession $session,
        AcademicYearSemester $target,
        array $ids,
        ?int $excludedCourseId = null,
    ): array {
        $ids = collect($ids)
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        $valid = CourseOffering::query()
            ->availableAsPrerequisiteFor($set, $session, $target)
            ->whereIn('course_id', $ids)
            ->pluck('course_id')
            ->unique();

        if ($excludedCourseId !== null) {
            $valid = $valid->reject(fn ($id): bool => (int) $id === $excludedCourseId);
        }

        if ($valid->count() !== $ids->count()) {
            throw ValidationException::withMessages([
                'prerequisite_course_ids' => 'Choose prerequisites from courses offered to this set in an earlier semester.',
            ]);
        }

        return $valid->map(fn ($id): int => (int) $id)->all();
    }

    private function ensureOfferingContext(
        CourseOffering $offering,
        StudentSet $set,
        AcademicSession $session,
        AcademicYearSemester $slot,
    ): void {
        abort_unless(
            $offering->student_set_id === $set->id
                && $offering->academic_session_id === $session->id
                && $offering->academic_year_semester_id === $slot->id,
            404,
        );
    }
}
