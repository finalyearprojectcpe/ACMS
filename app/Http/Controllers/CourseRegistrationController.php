<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCourseRegistrationRequest;
use App\Models\AcademicSessionSemester;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\StudentProfile;
use App\Services\CourseRegistrationPlanner;
use App\Services\DashboardAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CourseRegistrationController extends Controller
{
    public function __construct(private readonly DashboardAccess $access) {}

    public function store(
        StoreCourseRegistrationRequest $request,
        CourseRegistrationPlanner $planner,
        string $departmentSlug,
        string $entrySession,
        string $sessionYears,
        string $semester,
        string $registrationNumber,
    ): RedirectResponse {
        $advisor = $this->access->advisor($request);
        $set = $this->access->studentSetByRoute($advisor, $departmentSlug, $entrySession);
        $student = $set->students()
            ->where('registration_number', StudentProfile::registrationNumberFromRoute($registrationNumber))
            ->firstOrFail();
        $period = $this->access->semesterByRoute($set, $sessionYears, $semester);
        $validated = $request->validated();

        DB::transaction(function () use ($advisor, $student, $period, $validated, $planner): void {
            $lockedPeriod = AcademicSessionSemester::query()->lockForUpdate()->findOrFail($period->id);
            $selected = collect($planner->selectedOptionsFromPayload(
                $planner->plan($student, $lockedPeriod),
                $validated['items'],
                (int) $lockedPeriod->maximum_course_units,
            ))->map(function (array $course) use ($lockedPeriod): array {
                $source = CourseOffering::query()->with('prerequisiteCourses')->findOrFail($course['courseOfferingId']);
                if ((int) $source->academic_session_semester_id === (int) $lockedPeriod->id) {
                    return $course;
                }

                $offering = $lockedPeriod->courseOfferings()->firstOrCreate(
                    ['course_id' => $course['courseId']],
                    [
                        'course_title' => $source->course_title,
                        'credit_units' => $source->credit_units,
                        'is_elective' => $source->is_elective,
                        'is_retake_only' => true,
                    ],
                );
                if ($offering->wasRecentlyCreated) {
                    $offering->prerequisiteCourses()->sync($source->prerequisiteCourses->modelKeys());
                }

                $course['courseOfferingId'] = (int) $offering->id;

                return $course;
            })->keyBy('courseOfferingId');
            $registration = CourseRegistration::query()->firstOrCreate([
                'student_profile_id' => $student->id,
                'academic_session_semester_id' => $lockedPeriod->id,
            ]);
            if ($registration->status === CourseRegistration::StatusAssigned && $validated['action'] === 'draft') {
                throw ValidationException::withMessages([
                    'action' => 'Assigned courses cannot be moved back to a draft.',
                ]);
            }
            $existing = $registration->items()->with('courseOffering')->get()->keyBy('course_offering_id');

            foreach ($existing as $offeringId => $item) {
                if ($selected->has($offeringId)) {
                    continue;
                }

                if ($item->grade !== null || $item->missing_script || $item->courseOffering?->results_published_at !== null) {
                    throw ValidationException::withMessages([
                        'items' => "{$item->course_code} has a result and cannot be removed from this assignment.",
                    ]);
                }

                $item->delete();
            }

            foreach ($selected as $offeringId => $course) {
                if ($existing->has($offeringId)) {
                    continue;
                }

                if ($lockedPeriod->courseOfferings()->whereKey($offeringId)->whereNotNull('results_published_at')->exists()) {
                    throw ValidationException::withMessages([
                        'items' => "{$course['code']} has already been published and cannot receive new students.",
                    ]);
                }

                $registration->items()->create([
                    'course_offering_id' => $offeringId,
                    'is_elective' => $course['isElective'],
                    'assignment_origin' => $course['assignmentOrigin'],
                    'course_code' => $course['code'],
                    'course_title' => $course['title'],
                    'credit_units' => $course['units'],
                ]);
            }

            $assign = $validated['action'] === 'assign';
            $registration->forceFill([
                'status' => $assign ? CourseRegistration::StatusAssigned : CourseRegistration::StatusDraft,
                'assigned_by_user_id' => $assign ? $advisor->user_id : null,
                'assigned_at' => $assign ? now() : null,
            ])->save();
        });

        $destination = match ($validated['from'] ?? null) {
            'home' => to_route('dashboard'),
            'profile' => to_route('dashboard.students.show', ['registrationNumber' => $student->routeRegistrationNumber()]),
            default => to_route('student-sets.courses.show', [
                ...$set->routeParameters(), 'session' => $sessionYears, 'semester' => $semester,
            ]),
        };

        return $destination->with('success', $validated['action'] === 'assign' ? 'Courses assigned to student.' : 'Course plan saved.');
    }
}
