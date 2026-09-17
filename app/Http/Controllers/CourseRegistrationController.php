<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReturnCourseRegistrationRequest;
use App\Http\Requests\StoreCourseRegistrationRequest;
use App\Models\AcademicSession;
use App\Models\AcademicYear;
use App\Models\AcademicYearSemester;
use App\Models\CourseRegistration;
use App\Models\CourseRegistrationItem;
use App\Models\Semester;
use App\Models\StudentProfile;
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
        [$session, $slot] = $this->currentRegistrationContext($student);
        $validated = $request->validated();
        $selected = collect($planner->selectedOptionsFromPayload(
            $planner->plan($student, $session, $slot),
            $validated['items'],
        ));
        $maximumUnits = (int) $student->studentSet->maximum_course_units;

        if ((int) $selected->sum('units') > $maximumUnits) {
            throw ValidationException::withMessages([
                'items' => "Selected courses cannot be more than {$maximumUnits} units for this semester.",
            ]);
        }

        $registration = CourseRegistration::query()->firstOrNew([
            'student_profile_id' => $student->id,
            'academic_session_id' => $session->id,
            'academic_year_semester_id' => $slot->id,
        ]);

        if ($registration->exists && ! $registration->isEditableByStudent()) {
            throw ValidationException::withMessages([
                'items' => 'This registration is already with your course adviser and cannot be changed yet.',
            ]);
        }

        DB::transaction(function () use ($student, $session, $slot, $registration, $selected, $validated): void {
            $isSubmit = $validated['action'] === 'submit';
            $registration->forceFill([
                'advisor_profile_id' => $student->advisor_profile_id,
                'student_set_id' => $student->student_set_id,
                'student_profile_id' => $student->id,
                'academic_session_id' => $session->id,
                'academic_year_semester_id' => $slot->id,
                'reviewer_advisor_profile_id' => $student->advisor_profile_id,
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
                    'category' => $course['categoryValue'],
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
        CourseRegistration $courseRegistration,
        CourseRegistrationPlanner $planner,
    ): RedirectResponse {
        $advisor = $this->access->advisor($request);
        $this->access->ensureOwnsRegistration($advisor, $courseRegistration);
        abort_unless($courseRegistration->status === CourseRegistration::StatusSubmitted, 404);
        $courseRegistration->loadMissing([
            'studentProfile',
            'academicSession',
            'academicYearSemester',
            'items',
            'studentSet',
        ]);
        $selected = $planner->selectedOptionsFromPayload(
            $planner->plan(
                $courseRegistration->studentProfile,
                $courseRegistration->academicSession,
                $courseRegistration->academicYearSemester,
            ),
            $courseRegistration->items->map(fn (CourseRegistrationItem $item): array => [
                'course_offering_id' => (int) $item->course_offering_id,
            ])->all(),
        );

        if ((int) collect($selected)->sum('units') > (int) $courseRegistration->studentSet->maximum_course_units) {
            throw ValidationException::withMessages([
                'registration' => 'This registration is above the course-unit limit. Return it to the student for correction.',
            ]);
        }

        $courseRegistration->forceFill([
            'status' => CourseRegistration::StatusApproved,
            'reviewer_advisor_profile_id' => $advisor->id,
            'feedback_note' => null,
            'reviewed_at' => now(),
        ])->save();

        return to_route('dashboard.course-registration.show', ['courseRegistration' => $courseRegistration])
            ->with('success', 'Course registration approved.');
    }

    public function returnForCorrection(
        ReturnCourseRegistrationRequest $request,
        CourseRegistration $courseRegistration,
    ): RedirectResponse {
        $advisor = $this->access->advisor($request);
        $this->access->ensureOwnsRegistration($advisor, $courseRegistration);
        abort_unless($courseRegistration->status === CourseRegistration::StatusSubmitted, 404);
        $courseRegistration->forceFill([
            'status' => CourseRegistration::StatusReturned,
            'reviewer_advisor_profile_id' => $advisor->id,
            'feedback_note' => $request->string('feedback_note')->trim()->toString()
                ?: 'Review the selected courses and submit the registration again.',
            'reviewed_at' => now(),
        ])->save();

        return to_route('dashboard.course-registration.show', ['courseRegistration' => $courseRegistration])
            ->with('success', 'Course registration returned to the student.');
    }

    /** @return array{0: AcademicSession, 1: AcademicYearSemester} */
    private function currentRegistrationContext(StudentProfile $student): array
    {
        $session = $this->academicContext->currentSession($student->advisorProfile)
            ?? $student->advisorProfile->academicSessions()->latest('starts_on')->first();
        $year = $session instanceof AcademicSession
            ? $this->academicContext->academicYearForStudent($student, $session)
            : null;
        $semester = $session instanceof AcademicSession
            ? $this->academicContext->currentSemesterInstance($session)?->semester
            : null;
        $semester ??= Semester::query()->orderBy('sort_order')->first();

        if (! ($session instanceof AcademicSession)
            || ! ($year instanceof AcademicYear)
            || ! ($semester instanceof Semester)) {
            throw ValidationException::withMessages([
                'registration' => 'Course registration is not available until the current academic session and semester are configured.',
            ]);
        }

        $slot = AcademicYearSemester::query()
            ->whereBelongsTo($year)
            ->whereBelongsTo($semester)
            ->firstOrFail()
            ->loadMissing(['academicYear', 'semester']);

        return [$session, $slot];
    }
}
