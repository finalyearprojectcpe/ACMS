<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReturnCourseRegistrationRequest;
use App\Http\Requests\StoreCourseRegistrationRequest;
use App\Models\CourseRegistration;
use App\Models\CourseRegistrationItem;
use App\Services\AcademicContextService;
use App\Services\CourseRegistrationPlanner;
use App\Services\DashboardAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CourseRegistrationController extends Controller
{
    public function __construct(
        private readonly DashboardAccess $access,
        private readonly AcademicContextService $academicContext,
    ) {}

    public function store(
        StoreCourseRegistrationRequest $request,
        CourseRegistrationPlanner $planner,
    ): RedirectResponse {
        $student = $this->access->student($request);
        $semester = $this->academicContext->requireRegistrationContext($student);
        $validated = $request->validated();
        $selected = collect($planner->selectedOptionsFromPayload(
            $planner->plan($student, $semester),
            $validated['items'],
            $semester->maximum_course_units,
        ));

        $registration = CourseRegistration::query()->firstOrNew([
            'student_profile_id' => $student->id,
            'academic_session_semester_id' => $semester->id,
        ]);

        if ($registration->exists && ! $registration->isEditableByStudent()) {
            throw ValidationException::withMessages([
                'items' => 'This registration is already with your course adviser and cannot be changed yet.',
            ]);
        }

        DB::transaction(function () use ($student, $semester, $registration, $selected, $validated): void {
            $isSubmit = $validated['action'] === 'submit';
            $registration->forceFill([
                'student_profile_id' => $student->id,
                'academic_session_semester_id' => $semester->id,
                'status' => $isSubmit
                    ? CourseRegistration::StatusSubmitted
                    : CourseRegistration::StatusDraft,
                'feedback_note' => $isSubmit ? null : $registration->feedback_note,
                'submitted_at' => $isSubmit ? now() : null,
                'reviewed_at' => null,
            ])->save();
            $registration->items()->delete();

            foreach ($selected as $course) {
                $registration->items()->create([
                    'course_offering_id' => $course['courseOfferingId'],
                    'is_elective' => $course['isElective'],
                    'course_code' => $course['code'],
                    'course_title' => $course['title'],
                    'credit_units' => $course['units'],
                ]);
            }
        });

        return to_route('dashboard.section', ['section' => 'course-registration'])
            ->with('success', $validated['action'] === 'submit'
                ? 'Course registration sent to your course adviser.'
                : 'Course registration saved.');
    }

    public function approve(
        Request $request,
        CourseRegistrationPlanner $planner,
        string $registrationNumber,
        string $sessionYears,
        string $semester,
    ): RedirectResponse {
        $advisor = $this->access->advisor($request);
        $courseRegistration = $this->access->registrationByRoute($advisor, $registrationNumber, $sessionYears, $semester);
        abort_unless($courseRegistration->status === CourseRegistration::StatusSubmitted, 404);
        $courseRegistration->loadMissing([
            'studentProfile',
            'academicSessionSemester.academicSession',
            'items',
        ]);
        if ($courseRegistration->academicSessionSemester->results_published_at !== null) {
            throw ValidationException::withMessages([
                'registration' => 'Results for this semester are already published. This registration cannot be approved.',
            ]);
        }
        $planner->selectedOptionsFromPayload(
            $planner->plan(
                $courseRegistration->studentProfile,
                $courseRegistration->academicSessionSemester,
            ),
            $courseRegistration->items->map(fn (CourseRegistrationItem $item): array => [
                'course_offering_id' => (int) $item->course_offering_id,
            ])->all(),
            $courseRegistration->academicSessionSemester->maximum_course_units,
        );

        $courseRegistration->forceFill([
            'status' => CourseRegistration::StatusApproved,
            'feedback_note' => null,
            'reviewed_at' => now(),
        ])->save();

        return to_route('dashboard.course-registration.show', $courseRegistration->reviewRouteParameters())
            ->with('success', 'Course registration approved.');
    }

    public function returnForCorrection(
        ReturnCourseRegistrationRequest $request,
        string $registrationNumber,
        string $sessionYears,
        string $semester,
    ): RedirectResponse {
        $advisor = $this->access->advisor($request);
        $courseRegistration = $this->access->registrationByRoute($advisor, $registrationNumber, $sessionYears, $semester);
        abort_unless($courseRegistration->status === CourseRegistration::StatusSubmitted, 404);
        $courseRegistration->forceFill([
            'status' => CourseRegistration::StatusReturned,
            'feedback_note' => $request->string('feedback_note')->trim()->toString()
                ?: 'Review the selected courses and submit the registration again.',
            'reviewed_at' => now(),
        ])->save();

        return to_route('dashboard.course-registration.show', $courseRegistration->reviewRouteParameters())
            ->with('success', 'Course registration returned to the student.');
    }
}
