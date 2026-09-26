<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdviserCourseOfferingRequest;
use App\Models\AcademicSessionSemester;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\StudentSet;
use App\Services\DashboardAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CourseOfferingController extends Controller
{
    public function __construct(private readonly DashboardAccess $access) {}

    public function store(
        AdviserCourseOfferingRequest $request,
        string $departmentSlug,
        string $entrySession,
        string $sessionYears,
        string $semester,
    ): RedirectResponse {
        $set = $this->ownedSet($request, $departmentSlug, $entrySession);
        $academicSessionSemester = $this->access->semesterByRoute($set, $sessionYears, $semester);
        $validated = $request->validated();

        $course = $set->courses()->where('code', $validated['code'])->first();

        if ($course instanceof Course && $academicSessionSemester->courseOfferings()
            ->whereBelongsTo($course)
            ->exists()) {
            throw ValidationException::withMessages([
                'code' => 'This course is already part of the selected semester.',
            ]);
        }

        DB::transaction(function () use ($set, $academicSessionSemester, $validated, $course): void {
            if (! $course instanceof Course) {
                $course = $set->courses()->create([
                    'code' => $validated['code'],
                    'title' => $validated['title'],
                    'slug' => Str::slug($validated['code'].' '.$validated['title']),
                ]);
            } else {
                $course->update(['title' => $validated['title']]);
            }

            $offering = $academicSessionSemester->courseOfferings()->create([
                'course_id' => $course->id,
                'course_title' => $validated['title'],
                'credit_units' => (int) $validated['credit_units'],
                'is_elective' => $validated['is_elective'],
            ]);
            $offering->prerequisiteCourses()->sync($this->validPrerequisiteIds(
                $set,
                $academicSessionSemester,
                $validated['prerequisite_course_ids'] ?? [],
            ));
        });

        return $this->courseScheduleRedirect($set, $academicSessionSemester)
            ->with('success', 'Course added to the semester.');
    }

    public function update(
        AdviserCourseOfferingRequest $request,
        string $departmentSlug,
        string $entrySession,
        string $sessionYears,
        string $semester,
        string $courseCode,
    ): RedirectResponse {
        $set = $this->ownedSet($request, $departmentSlug, $entrySession);
        $period = $this->access->semesterByRoute($set, $sessionYears, $semester);
        $courseOffering = $this->access->courseOfferingByRoute($set, $period, $courseCode);
        $validated = $request->validated();

        if ($set->courses()
            ->where('code', $validated['code'])
            ->whereKeyNot($courseOffering->course_id)
            ->exists()) {
            throw ValidationException::withMessages([
                'code' => 'Another course in this set already uses this code.',
            ]);
        }

        DB::transaction(function () use ($set, $courseOffering, $validated): void {
            $courseOffering->course->update([
                'code' => $validated['code'],
                'title' => $validated['title'],
                'slug' => Str::slug($validated['code'].' '.$validated['title']),
            ]);
            $courseOffering->update([
                'course_title' => $validated['title'],
                'credit_units' => (int) $validated['credit_units'],
                'is_elective' => $validated['is_elective'],
            ]);
            $courseOffering->prerequisiteCourses()->sync($this->validPrerequisiteIds(
                $set,
                $courseOffering->academicSessionSemester,
                $validated['prerequisite_course_ids'] ?? [],
                (int) $courseOffering->course_id,
            ));
        });

        return $this->courseScheduleRedirect($set, $courseOffering->academicSessionSemester)
            ->with('success', 'Course details updated.');
    }

    public function destroy(
        Request $request,
        string $departmentSlug,
        string $entrySession,
        string $sessionYears,
        string $semester,
        string $courseCode,
    ): RedirectResponse {
        $set = $this->ownedSet($request, $departmentSlug, $entrySession);
        $period = $this->access->semesterByRoute($set, $sessionYears, $semester);
        $courseOffering = $this->access->courseOfferingByRoute($set, $period, $courseCode);

        if ($courseOffering->registrationItems()->exists()) {
            throw ValidationException::withMessages([
                'course' => 'This course is already included in a student registration and cannot be removed.',
            ]);
        }

        $semester = $courseOffering->academicSessionSemester;
        DB::transaction(function () use ($courseOffering): void {
            $course = $courseOffering->course;
            $courseOffering->delete();

            if (! $course->offerings()->exists()) {
                $course->delete();
            }
        });

        return $this->courseScheduleRedirect($set, $semester)
            ->with('success', 'Course removed from the semester.');
    }

    /** @param array<int, int|string> $ids
     * @return array<int, int>
     */
    private function validPrerequisiteIds(
        StudentSet $set,
        AcademicSessionSemester $target,
        array $ids,
        ?int $excludedCourseId = null,
    ): array {
        $ids = collect($ids)->map(fn ($id): int => (int) $id)->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return [];
        }

        $valid = CourseOffering::query()
            ->availableAsPrerequisiteFor($set, $target)
            ->whereIn('course_id', $ids)
            ->pluck('course_id')
            ->unique();

        if ($excludedCourseId !== null) {
            $valid = $valid->reject(fn ($id): bool => (int) $id === $excludedCourseId);
        }

        if ($valid->count() !== $ids->count()) {
            throw ValidationException::withMessages([
                'prerequisite_course_ids' => 'Choose prerequisites from courses this set took in an earlier semester.',
            ]);
        }

        return $valid->map(fn ($id): int => (int) $id)->all();
    }

    private function ownedSet(Request $request, string $departmentSlug, string $entrySession): StudentSet
    {
        return $this->access->studentSetByRoute(
            $this->access->advisor($request),
            $departmentSlug,
            $entrySession,
        );
    }

    private function courseScheduleRedirect(
        StudentSet $set,
        AcademicSessionSemester $semester,
    ): RedirectResponse {
        return to_route('student-sets.courses.show', [
            ...$set->routeParameters(),
            'session' => $semester->academicSession->routeYears(),
            'semester' => $semester->semester->slug,
        ]);
    }
}
