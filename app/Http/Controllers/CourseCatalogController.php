<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCourseCatalogRequest;
use App\Http\Requests\UpdateCatalogCourseRequest;
use App\Models\Course;
use App\Models\StudentSet;
use App\Services\DashboardAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CourseCatalogController extends Controller
{
    public function __construct(private readonly DashboardAccess $access) {}

    public function store(StoreCourseCatalogRequest $request, string $departmentSlug, string $entrySession): RedirectResponse
    {
        $set = $this->ownedSet($request, $departmentSlug, $entrySession);
        $courses = collect($request->validated('courses'));
        $duplicate = $courses->pluck('code')->duplicates()->first();

        if ($duplicate !== null || $set->courses()->whereIn('code', $courses->pluck('code'))->exists()) {
            throw ValidationException::withMessages(['courses' => 'A course code is duplicated or already exists in this set.']);
        }

        DB::transaction(function () use ($set, $courses): void {
            foreach ($courses as $course) {
                $set->courses()->create($course);
            }
        });

        return to_route('student-sets.catalog.show', $set->routeParameters())->with('success', 'Courses added to catalog.');
    }

    public function update(UpdateCatalogCourseRequest $request, string $departmentSlug, string $entrySession, string $courseCode): RedirectResponse
    {
        $set = $this->ownedSet($request, $departmentSlug, $entrySession);
        $course = $this->course($set, $courseCode);
        $validated = $request->validated();

        if ($course->offerings()->whereHas('registrationItems')->exists() && $course->code !== $validated['code']) {
            throw ValidationException::withMessages(['code' => 'This course code is already used in student records.']);
        }

        if ($set->courses()->where('code', $validated['code'])->whereKeyNot($course->id)->exists()) {
            throw ValidationException::withMessages(['code' => 'Another course in this set uses this code.']);
        }

        $course->update($validated);

        return to_route('student-sets.catalog.show', $set->routeParameters())->with('success', 'Catalog course updated. Scheduled courses keep their existing details.');
    }

    public function destroy(Request $request, string $departmentSlug, string $entrySession, string $courseCode): RedirectResponse
    {
        $set = $this->ownedSet($request, $departmentSlug, $entrySession);
        $course = $this->course($set, $courseCode);

        if ($course->offerings()->exists()) {
            throw ValidationException::withMessages(['course' => 'Remove this course from its semesters before deleting it from the catalog.']);
        }

        $course->delete();

        return to_route('student-sets.catalog.show', $set->routeParameters())->with('success', 'Catalog course removed.');
    }

    private function ownedSet(Request $request, string $departmentSlug, string $entrySession): StudentSet
    {
        return $this->access->studentSetByRoute($this->access->advisor($request), $departmentSlug, $entrySession);
    }

    private function course(StudentSet $set, string $courseCode): Course
    {
        $code = Course::codeFromUrl($courseCode);
        abort_unless($code !== null, 404);

        return $set->courses()->where('code', $code)->firstOrFail();
    }
}
