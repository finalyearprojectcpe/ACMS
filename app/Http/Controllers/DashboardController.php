<?php

namespace App\Http\Controllers;

use App\AccountStatus;
use App\AccountType;
use App\CourseCategory;
use App\Http\Requests\ImportAdvisedStudentsRequest;
use App\Http\Requests\ResultSheetImportRequest;
use App\Http\Requests\ReturnCourseRegistrationRequest;
use App\Http\Requests\StaffCourseOfferingRequest;
use App\Http\Requests\StoreAdvisedStudentRequest;
use App\Http\Requests\StoreCourseRegistrationRequest;
use App\Http\Requests\StoreStaffDueRequest;
use App\Http\Requests\StoreStaffPaymentAccountRequest;
use App\Http\Requests\UpdateProfilePhotoRequest;
use App\Http\Requests\UpdateStaffPasswordRequest;
use App\Http\Requests\UpdateStudentProfileRequest;
use App\Http\Requests\WaiveStudentDueRequest;
use App\Models\AcademicSession;
use App\Models\AcademicYear;
use App\Models\AcademicYearSemester;
use App\Models\AdvisorProfile;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\CourseRegistrationItem;
use App\Models\Due;
use App\Models\DuePayment;
use App\Models\EmailDelivery;
use App\Models\ImportBatch;
use App\Models\PaymentAccount;
use App\Models\ResultSheet;
use App\Models\ResultSheetCourse;
use App\Models\Semester;
use App\Models\StudentDueAssignment;
use App\Models\StudentProfile;
use App\Models\StudentSemesterResult;
use App\Models\StudentSet;
use App\Models\User;
use App\Services\AcademicContextService;
use App\Services\AcademicPerformanceService;
use App\Services\CourseRegistrationPlanner;
use App\Services\DueAssignmentService;
use App\Services\DuePaymentService;
use App\Services\DueReceiptService;
use App\Services\PaystackException;
use App\Services\PaystackService;
use App\Services\PaystackWebhookService;
use App\Services\ResultSheetImporter;
use App\Services\Spreadsheet\DuePaymentsWorkbook;
use App\Services\Spreadsheet\ResultSheetWorkbook;
use App\Services\Spreadsheet\SpreadsheetExportService;
use App\Services\Spreadsheet\StudentClasslistWorkbook;
use App\Services\Spreadsheet\StudentStatementWorkbook;
use App\StudentStatus;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DashboardController extends Controller
{
    private const array ROLE_SECTIONS = [
        'staff' => ['home', 'students', 'courses', 'course-registration', 'result-management', 'dues', 'payment-settings', 'profile-settings'],
        'student' => ['home', 'results', 'dues', 'course-registration', 'profile-settings'],
    ];

    private const array SECTION_TITLES = [
        'home' => 'Home',
        'students' => 'Students',
        'courses' => 'Course Offerings',
        'course-registration' => 'Course Registrations',
        'result-management' => 'Result Management',
        'dues' => 'Dues',
        'payment-settings' => 'Paystack Settings',
        'profile-settings' => 'Profile Settings',
        'results' => 'Results',
    ];

    public function __construct(
        private readonly AcademicContextService $academicContext,
        private readonly AcademicPerformanceService $academicPerformance,
        private readonly DueAssignmentService $dueAssignments,
        private readonly DueReceiptService $dueReceipts,
    ) {}

    public function home(Request $request): InertiaResponse
    {
        return $this->renderDashboard($request, 'home');
    }

    public function section(Request $request, string $section): InertiaResponse
    {
        return $this->renderDashboard($request, $section);
    }

    public function level(Request $request, string $level): InertiaResponse
    {
        return $this->renderDashboard($request, 'results', $level);
    }

    public function createAdvisedStudent(Request $request): InertiaResponse
    {
        $advisor = $this->authorizedAdvisor($request);

        return $this->renderDashboard($request, 'students', overrides: [
            'staffStudentForm' => $this->studentFormPayload($advisor, 'create'),
        ]);
    }

    public function importAdvisedStudents(Request $request): InertiaResponse
    {
        $advisor = $this->authorizedAdvisor($request);

        return $this->renderDashboard($request, 'students', overrides: [
            'staffStudentForm' => $this->studentFormPayload($advisor, 'import'),
        ]);
    }

    public function storeAdvisedStudent(StoreAdvisedStudentRequest $request): RedirectResponse
    {
        $advisor = $this->authorizedAdvisor($request);
        $set = $this->advisorStudentSet($advisor, $request->integer('student_set_id'));
        $validated = $request->validated();
        $student = DB::transaction(fn (): StudentProfile => $this->provisionStudent(
            $advisor,
            $set,
            (string) $validated['registration_number'],
            $validated['access_code'] ?? null,
        ));

        return to_route('dashboard.section', ['section' => 'students'])
            ->with('success', "Student {$student->registration_number} added. Their access code is ready to copy.");
    }

    public function storeImportedAdvisedStudents(ImportAdvisedStudentsRequest $request): RedirectResponse
    {
        $advisor = $this->authorizedAdvisor($request);
        $set = $this->advisorStudentSet($advisor, $request->integer('student_set_id'));
        $validated = $request->validated();
        $rows = collect($validated['rows']);
        $batchNumber = (int) ($validated['batch_number'] ?? 1);
        $startRow = (int) ($validated['start_row'] ?? 1);
        $totalRows = (int) ($validated['total_rows'] ?? $rows->count());
        $totalBatches = (int) ($validated['total_batches'] ?? 1);

        $importedCount = DB::transaction(function () use ($advisor, $set, $request, $validated, $rows, $batchNumber, $startRow, $totalRows, $totalBatches): int {
            $batch = ImportBatch::query()->firstOrCreate([
                'advisor_profile_id' => $advisor->id,
                'uploaded_by_user_id' => $request->user()->id,
                'type' => 'student_import',
                'import_key' => $validated['import_key'] ?: null,
            ], [
                'student_set_id' => $set->id,
                'status' => 'completed',
                'file_name' => $validated['file_name'] ?: 'Students spreadsheet',
                'mappings' => $validated['mappings'] ?? [],
                'summary' => [],
                'submitted_at' => now(),
                'approved_at' => now(),
            ]);

            foreach ($rows as $index => $row) {
                $rowNumber = $startRow + $index;

                if ($batch->rows()->where('row_number', $rowNumber)->where('status', 'imported')->exists()) {
                    continue;
                }

                $student = $this->provisionStudent(
                    $advisor,
                    $set,
                    (string) $row['registrationNumber'],
                    $row['accessCode'] ?: null,
                );
                $batch->rows()->updateOrCreate(['row_number' => $rowNumber], [
                    'raw_data' => $row,
                    'normalized_data' => [
                        'student_profile_id' => $student->id,
                        'registration_number' => $student->registration_number,
                        'access_code' => $student->user->access_code,
                    ],
                    'errors' => [],
                    'status' => 'imported',
                ]);
            }

            $count = $batch->rows()->where('status', 'imported')->count();
            $batch->forceFill([
                'student_set_id' => $set->id,
                'mappings' => $validated['mappings'] ?? $batch->mappings,
                'summary' => [
                    'submitted_rows' => $totalRows,
                    'total_batches' => $totalBatches,
                    'processed_batches' => $batchNumber,
                    'imported_students' => $count,
                ],
            ])->save();

            return $count;
        });

        if ($batchNumber < $totalBatches) {
            return back()->with('success', "Batch {$batchNumber} of {$totalBatches} imported. Continue with the next batch.");
        }

        return to_route('dashboard.section', ['section' => 'students'])
            ->with('success', "{$importedCount} student records imported.");
    }

    public function showStudentProfile(Request $request, string $registrationNumber): InertiaResponse
    {
        $advisor = $this->authorizedAdvisor($request);
        $student = $advisor->students()
            ->with(['user', 'currentLevel', 'studentSet.entryAcademicSession'])
            ->where('registration_number', $registrationNumber)
            ->firstOrFail();

        return $this->renderDashboard($request, 'students', overrides: [
            'studentRecord' => $this->studentRecordPayload($student),
        ]);
    }

    public function downloadStudentStatement(
        Request $request,
        string $registrationNumber,
        StudentStatementWorkbook $workbook,
        SpreadsheetExportService $exports,
    ): StreamedResponse {
        $advisor = $this->authorizedAdvisor($request);
        $student = $advisor->students()->where('registration_number', $registrationNumber)->firstOrFail();

        return $exports->download(
            $workbook->build($student, $advisor),
            Str::slug($student->registration_number).'-statement-of-results.xlsx',
        );
    }

    public function exportStudentClasslist(
        Request $request,
        StudentClasslistWorkbook $workbook,
        SpreadsheetExportService $exports,
    ): StreamedResponse {
        $advisor = $this->authorizedAdvisor($request);
        $setId = $request->integer('set');
        $students = $advisor->students()
            ->with(['user', 'studentSet.entryAcademicSession'])
            ->when($setId > 0, fn ($query) => $query->where('student_set_id', $setId))
            ->get()
            ->sortBy(fn (StudentProfile $student): string => $student->user->name)
            ->values()
            ->map(fn (StudentProfile $student): array => [
                'fullName' => $student->user->name,
                'email' => $student->user->email,
                'registrationNumber' => $student->registration_number,
                'setLabel' => $student->studentSet->name,
                'advisorName' => $advisor->user->name,
            ]);
        $scope = $setId > 0 ? $advisor->studentSets()->findOrFail($setId)->name : 'All student sets';

        return $exports->download(
            $workbook->build($advisor, $students, [
                'scope' => $scope,
                'courseAdvisor' => $advisor->user->name,
                'filters' => null,
                'includeContextColumns' => $setId === 0,
            ]),
            Str::lower($advisor->programme_abbreviation).'-student-classlist-'.now()->format('Y-m-d').'.xlsx',
        );
    }

    public function createCourseOffering(Request $request, string $level, string $semester): InertiaResponse
    {
        $advisor = $this->authorizedAdvisor($request);
        $set = $this->selectedAdvisorSet($request, $advisor);
        $academicYear = $this->resolveLevel($level, $set);
        $semesterModel = $this->resolveSemester($semester);
        $session = $this->requiredSessionForSetYear($set, $academicYear);

        return $this->renderDashboard($request, 'courses', overrides: [
            'staffCourseOfferingForm' => $this->courseOfferingFormPayload($advisor, $set, $session, $academicYear, $semesterModel),
        ]);
    }

    public function storeCourseOffering(
        StaffCourseOfferingRequest $request,
        string $level,
        string $semester,
    ): RedirectResponse {
        $advisor = $this->authorizedAdvisor($request);
        $set = $this->selectedAdvisorSet($request, $advisor);
        $year = $this->resolveLevel($level, $set);
        $semesterModel = $this->resolveSemester($semester);
        $session = $this->requiredSessionForSetYear($set, $year);
        $slot = $this->yearSemester($year, $semesterModel);
        $validated = $request->validated();
        $course = Course::query()->updateOrCreate(
            ['advisor_profile_id' => $advisor->id, 'code' => $validated['code']],
            ['title' => $validated['title'], 'slug' => Str::slug($validated['code'].' '.$validated['title']), 'credit_units' => (int) $validated['credit_units']],
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

        if ($request->query('return_to') === 'results-import') {
            return to_route('dashboard.results.import')->with('success', 'Course offering saved. You can continue the result import.');
        }

        return to_route('dashboard.section', [
            'section' => 'courses', 'set' => $set->id, 'level' => $year->slug, 'semester' => $semesterModel->slug,
        ])->with('success', 'Course offering saved.');
    }

    public function editCourseOffering(
        Request $request,
        string $level,
        string $semester,
        CourseOffering $offering,
    ): InertiaResponse {
        $advisor = $this->authorizedAdvisor($request);
        $set = $this->advisorStudentSet($advisor, (int) $offering->student_set_id);
        $year = $this->resolveLevel($level, $set);
        $semesterModel = $this->resolveSemester($semester);
        $session = $this->requiredSessionForSetYear($set, $year);
        $this->ensureOfferingContext($offering, $set, $session, $year, $semesterModel);

        return $this->renderDashboard($request, 'courses', overrides: [
            'staffCourseOfferingForm' => $this->courseOfferingFormPayload($advisor, $set, $session, $year, $semesterModel, $offering),
        ]);
    }

    public function updateCourseOffering(
        StaffCourseOfferingRequest $request,
        string $level,
        string $semester,
        CourseOffering $offering,
    ): RedirectResponse {
        $advisor = $this->authorizedAdvisor($request);
        $set = $this->advisorStudentSet($advisor, (int) $offering->student_set_id);
        $year = $this->resolveLevel($level, $set);
        $semesterModel = $this->resolveSemester($semester);
        $session = $this->requiredSessionForSetYear($set, $year);
        $slot = $this->yearSemester($year, $semesterModel);
        $this->ensureOfferingContext($offering, $set, $session, $year, $semesterModel);
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
            'section' => 'courses', 'set' => $set->id, 'level' => $year->slug, 'semester' => $semesterModel->slug,
        ])->with('success', 'Course offering updated.');
    }

    public function createResultSheetImport(Request $request): InertiaResponse
    {
        $advisor = $this->authorizedAdvisor($request);

        return $this->renderDashboard($request, 'result-management', overrides: [
            'staffResultImportForm' => $this->resultImportFormPayload($advisor),
        ]);
    }

    public function storeResultSheetImport(ResultSheetImportRequest $request, ResultSheetImporter $importer): RedirectResponse
    {
        $advisor = $this->authorizedAdvisor($request);
        $sheet = $importer->import($advisor, $request->validated());

        return to_route('dashboard.results.show', ['resultSheet' => $sheet])
            ->with('success', $sheet->status === ResultSheet::StatusPublished
                ? 'Results published. Students can now view them.'
                : 'Result sheet saved as a draft.');
    }

    public function showResultSheet(Request $request, ResultSheet $resultSheet): InertiaResponse
    {
        $user = $this->authorizedUser($request);
        $this->ensureCanViewResultSheet($user, $resultSheet);

        return $this->renderDashboard(
            $request,
            $user->account_type === AccountType::Adviser ? 'result-management' : 'results',
            overrides: ['resultSheetDetail' => $this->resultSheetDetailPayload($resultSheet, $user)],
        );
    }

    public function downloadResultSheetSourceFile(Request $request, ResultSheet $resultSheet): StreamedResponse
    {
        $advisor = $this->authorizedAdvisor($request);
        abort_unless((int) $resultSheet->advisor_profile_id === (int) $advisor->id, 404);
        $revision = $resultSheet->revisions()->whereNotNull('source_file_path')->latest('revision_number')->firstOrFail();
        abort_unless(Storage::disk($revision->source_file_disk)->exists($revision->source_file_path), 404);

        return Storage::disk($revision->source_file_disk)->download(
            $revision->source_file_path,
            $revision->source_file_name ?: 'result-source.xlsx',
        );
    }

    public function exportResultSheet(
        Request $request,
        ResultSheet $resultSheet,
        ResultSheetWorkbook $workbook,
        SpreadsheetExportService $exports,
    ): StreamedResponse {
        $user = $this->authorizedUser($request);
        $this->ensureCanViewResultSheet($user, $resultSheet);
        $resultSheet->loadMissing(['studentSet', 'academicYearSemester.academicYear', 'academicYearSemester.semester']);

        return $exports->download(
            $workbook->build($resultSheet),
            Str::slug($resultSheet->studentSet->name.' '.$resultSheet->academicYearSemester->academicYear->name.' '.$resultSheet->academicYearSemester->semester->name.' results').'.xlsx',
        );
    }

    public function storeCourseRegistration(
        StoreCourseRegistrationRequest $request,
        CourseRegistrationPlanner $planner,
    ): RedirectResponse {
        $student = $this->authorizedStudent($request);
        [$session, $slot] = $this->currentRegistrationContext($student);
        $validated = $request->validated();
        $selected = collect($planner->selectedOptionsFromPayload(
            $planner->plan($student, $session, $slot),
            $validated['items'],
            $validated['action'] === 'submit',
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
                'status' => $isSubmit ? CourseRegistration::StatusSubmitted : CourseRegistration::StatusDraft,
                'feedback_note' => $isSubmit ? null : $registration->feedback_note,
                'submitted_at' => $isSubmit ? now() : null,
                'reviewed_at' => null,
            ])->save();
            $registration->items()->delete();

            foreach ($selected as $course) {
                $registration->items()->create([
                    'course_offering_id' => $course['courseOfferingId'],
                    'source' => $course['source'],
                    'category' => $course['category'],
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

    public function showCourseRegistrationReview(Request $request, CourseRegistration $courseRegistration): InertiaResponse
    {
        $advisor = $this->authorizedAdvisor($request);
        $this->ensureAdvisorOwnsRegistration($advisor, $courseRegistration);

        return $this->renderDashboard($request, 'course-registration', overrides: [
            'staffCourseRegistration' => $this->staffCourseRegistrationPayload($advisor, $courseRegistration),
        ]);
    }

    public function approveCourseRegistration(
        Request $request,
        CourseRegistration $courseRegistration,
        CourseRegistrationPlanner $planner,
    ): RedirectResponse {
        $advisor = $this->authorizedAdvisor($request);
        $this->ensureAdvisorOwnsRegistration($advisor, $courseRegistration);
        abort_unless($courseRegistration->status === CourseRegistration::StatusSubmitted, 404);
        $courseRegistration->loadMissing(['studentProfile', 'academicSession', 'academicYearSemester', 'items', 'studentSet']);
        $selected = $planner->selectedOptionsFromPayload(
            $planner->plan($courseRegistration->studentProfile, $courseRegistration->academicSession, $courseRegistration->academicYearSemester),
            $courseRegistration->items->map(fn (CourseRegistrationItem $item): array => [
                'course_offering_id' => (int) $item->course_offering_id,
                'source' => $item->source,
            ])->all(),
            true,
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

    public function returnCourseRegistration(
        ReturnCourseRegistrationRequest $request,
        CourseRegistration $courseRegistration,
    ): RedirectResponse {
        $advisor = $this->authorizedAdvisor($request);
        $this->ensureAdvisorOwnsRegistration($advisor, $courseRegistration);
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

    public function createStaffDue(Request $request): InertiaResponse
    {
        $advisor = $this->authorizedAdvisor($request);

        return $this->renderDashboard($request, 'dues', overrides: [
            'staffDueForm' => $this->staffDueFormPayload($advisor),
        ]);
    }

    public function editStaffDue(Request $request, Due $due): InertiaResponse
    {
        $advisor = $this->authorizedAdvisor($request);
        $this->ensureAdvisorOwnsDue($advisor, $due);
        abort_if($due->status === Due::StatusArchived, 404);

        return $this->renderDashboard($request, 'dues', overrides: [
            'staffDueForm' => $this->staffDueFormPayload($advisor, $due),
        ]);
    }

    public function storeStaffDue(StoreStaffDueRequest $request): RedirectResponse
    {
        $advisor = $this->authorizedAdvisor($request);
        $set = $this->advisorStudentSet($advisor, $request->integer('student_set_id'));
        $validated = $request->validated();
        $session = $this->requiredCurrentSession($advisor);
        $isPublishing = $validated['action'] === 'publish';
        $account = PaymentAccount::currentFor($advisor);

        if ($isPublishing && ! $account instanceof PaymentAccount) {
            throw ValidationException::withMessages([
                'payment_account' => 'Connect a Paystack payout account before publishing this due.',
            ]);
        }

        $due = Due::query()->create([
            'advisor_profile_id' => $advisor->id,
            'student_set_id' => $set->id,
            'academic_session_id' => $session->id,
            'payment_account_id' => $account?->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'amount_kobo' => (int) $validated['amount_naira'] * 100,
            'currency' => 'NGN',
            'due_on' => $validated['due_on'] ?? null,
            'status' => $isPublishing ? Due::StatusPublished : Due::StatusDraft,
            'published_at' => $isPublishing ? now() : null,
        ]);

        if ($isPublishing) {
            $this->dueAssignments->syncForDue($due);
        }

        return to_route('dashboard.dues.show', ['due' => $due])
            ->with('success', $isPublishing ? 'Due published for the selected set.' : 'Due draft saved.');
    }

    public function updateStaffDue(StoreStaffDueRequest $request, Due $due): RedirectResponse
    {
        $advisor = $this->authorizedAdvisor($request);
        $this->ensureAdvisorOwnsDue($advisor, $due);
        abort_if($due->status === Due::StatusArchived, 404);
        $validated = $request->validated();
        $set = $this->advisorStudentSet($advisor, $request->integer('student_set_id'));
        $amountKobo = (int) $validated['amount_naira'] * 100;
        $wasPublished = $due->status === Due::StatusPublished;

        if ($wasPublished && (int) $due->student_set_id !== (int) $set->id) {
            throw ValidationException::withMessages([
                'student_set_id' => 'The student set cannot change after a due is published. Create a separate due for another set.',
            ]);
        }

        $isPublishing = ! $wasPublished && $validated['action'] === 'publish';
        $account = PaymentAccount::currentFor($advisor);

        if ($isPublishing && ! $account instanceof PaymentAccount) {
            throw ValidationException::withMessages([
                'payment_account' => 'Connect a Paystack payout account before publishing this due.',
            ]);
        }

        DB::transaction(function () use ($due, $set, $validated, $amountKobo, $wasPublished, $isPublishing, $account): void {
            $amountChanged = (int) $due->amount_kobo !== $amountKobo;
            $due->forceFill([
                'student_set_id' => $set->id,
                'payment_account_id' => $account?->id ?? $due->payment_account_id,
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'amount_kobo' => $amountKobo,
                'due_on' => $validated['due_on'] ?? null,
                'status' => $wasPublished || $isPublishing ? Due::StatusPublished : Due::StatusDraft,
                'published_at' => $wasPublished ? $due->published_at : ($isPublishing ? now() : null),
            ])->save();

            if ($isPublishing) {
                $this->dueAssignments->syncForDue($due);
            } elseif ($wasPublished && $amountChanged) {
                $this->dueAssignments->updateAmount($due, $amountKobo);
            }
        });

        return to_route('dashboard.dues.show', ['due' => $due])
            ->with('success', $isPublishing ? 'Due published for the selected set.' : 'Due details updated.');
    }

    public function showStaffDue(Request $request, Due $due): InertiaResponse
    {
        $advisor = $this->authorizedAdvisor($request);
        $this->ensureAdvisorOwnsDue($advisor, $due);

        return $this->renderDashboard($request, 'dues', overrides: [
            'staffDueDetail' => $this->staffDueDetailPayload($due),
        ]);
    }

    public function archiveStaffDue(Request $request, Due $due): RedirectResponse
    {
        $advisor = $this->authorizedAdvisor($request);
        $this->ensureAdvisorOwnsDue($advisor, $due);
        $this->dueAssignments->archive($due);

        return to_route('dashboard.dues.show', ['due' => $due])
            ->with('success', 'Due archived. Students can no longer start new payments for it.');
    }

    public function restoreStaffDue(Request $request, Due $due): RedirectResponse
    {
        $advisor = $this->authorizedAdvisor($request);
        $this->ensureAdvisorOwnsDue($advisor, $due);
        abort_unless($due->status === Due::StatusArchived, 404);
        $account = PaymentAccount::currentFor($advisor);

        if (! $account instanceof PaymentAccount) {
            throw ValidationException::withMessages([
                'payment_account' => 'Connect an active Paystack payout account before restoring this due.',
            ]);
        }

        $due->forceFill([
            'payment_account_id' => $account->id,
            'status' => Due::StatusPublished,
            'archived_at' => null,
            'published_at' => $due->published_at ?? now(),
        ])->save();
        $this->dueAssignments->syncForDue($due);

        return to_route('dashboard.dues.show', ['due' => $due])
            ->with('success', 'Due restored. Pending students can pay it again.');
    }

    public function createStudentDueWaiver(
        Request $request,
        Due $due,
        StudentDueAssignment $studentDueAssignment,
    ): InertiaResponse {
        $advisor = $this->authorizedAdvisor($request);
        $this->ensureAdvisorOwnsAssignment($advisor, $due, $studentDueAssignment);
        abort_unless($studentDueAssignment->status === StudentDueAssignment::StatusPending, 404);

        return $this->renderDashboard($request, 'dues', overrides: [
            'staffDueWaiverForm' => $this->staffDueWaiverFormPayload($due, $studentDueAssignment),
        ]);
    }

    public function waiveStudentDue(
        WaiveStudentDueRequest $request,
        Due $due,
        StudentDueAssignment $studentDueAssignment,
    ): RedirectResponse {
        $advisor = $this->authorizedAdvisor($request);
        $this->ensureAdvisorOwnsAssignment($advisor, $due, $studentDueAssignment);
        $this->dueAssignments->waive($studentDueAssignment, $request->user(), $request->string('reason')->trim()->toString() ?: null);

        return to_route('dashboard.dues.show', ['due' => $due])
            ->with('success', 'Payment requirement waived for this student.');
    }

    public function restoreStudentDue(
        Request $request,
        Due $due,
        StudentDueAssignment $studentDueAssignment,
    ): RedirectResponse {
        $advisor = $this->authorizedAdvisor($request);
        $this->ensureAdvisorOwnsAssignment($advisor, $due, $studentDueAssignment);
        abort_unless($due->status === Due::StatusPublished, 404);
        $this->dueAssignments->restore($studentDueAssignment);

        return to_route('dashboard.dues.show', ['due' => $due])
            ->with('success', 'Payment requirement restored for this student.');
    }

    public function exportStaffDue(
        Request $request,
        Due $due,
        DuePaymentsWorkbook $workbook,
        SpreadsheetExportService $exports,
    ): StreamedResponse {
        $advisor = $this->authorizedAdvisor($request);
        $this->ensureAdvisorOwnsDue($advisor, $due);

        return $exports->download(
            $workbook->build($due),
            Str::lower($advisor->programme_abbreviation).'-'.Str::slug($due->title).'-payments-'.now()->format('Y-m-d').'.xlsx',
        );
    }

    public function startDuePayment(
        Request $request,
        StudentDueAssignment $studentDueAssignment,
        DuePaymentService $payments,
    ): RedirectResponse|Response {
        $student = $this->authorizedStudent($request);
        abort_unless((int) $studentDueAssignment->student_profile_id === (int) $student->id, 404);
        $studentDueAssignment->loadMissing('due');
        abort_unless($studentDueAssignment->due?->status === Due::StatusPublished, 404);

        try {
            $payment = $payments->startPayment(
                $studentDueAssignment,
                fn (DuePayment $payment): string => route('dashboard.dues.payments.callback', ['duePayment' => $payment]),
            );
        } catch (ConnectionException|PaystackException|\RuntimeException) {
            return to_route('dashboard.section', ['section' => 'dues'])
                ->with('error', 'Payment is temporarily unavailable. Try again shortly or contact your course adviser.');
        }

        if ($payment->status === DuePayment::StatusSuccess) {
            return to_route('dashboard.section', ['section' => 'dues'])
                ->with('success', 'This due has already been paid.');
        }

        return Inertia::location((string) $payment->authorization_url);
    }

    public function handleDuePaymentCallback(
        Request $request,
        DuePayment $duePayment,
        DuePaymentService $payments,
    ): RedirectResponse {
        $student = $this->authorizedStudent($request);
        abort_unless((int) $duePayment->student_profile_id === (int) $student->id, 404);
        $reference = $request->string('reference')->toString();
        abort_unless($reference === '' || $reference === $duePayment->reference, 404);

        try {
            $payment = $payments->verifyPayment($duePayment);
        } catch (\Throwable) {
            return to_route('dashboard.section', ['section' => 'dues'])
                ->with('error', 'Payment could not be confirmed yet. If money left your account, check again shortly.');
        }

        return to_route('dashboard.section', ['section' => 'dues'])
            ->with(
                $payment->status === DuePayment::StatusSuccess ? 'success' : 'error',
                $payment->status === DuePayment::StatusSuccess
                    ? 'Payment received. Your receipt is being prepared and will be emailed to you.'
                    : 'Payment was not completed.',
            );
    }

    public function downloadDuePaymentReceipt(
        Request $request,
        string $paystackTransactionId,
        DueReceiptService $receipts,
    ): RedirectResponse|StreamedResponse {
        $user = $this->authorizedUser($request);
        $payment = DuePayment::query()->where('paystack_transaction_id', $paystackTransactionId)->firstOrFail();
        abort_unless($this->userCanViewPayment($user, $payment), 404);
        abort_unless(in_array($payment->status, [DuePayment::StatusSuccess, DuePayment::StatusReversed], true), 404);

        if (
            $payment->receipt_status !== DuePayment::ReceiptStatusReady
            || ! is_string($payment->receipt_disk)
            || ! is_string($payment->receipt_path)
            || ! Storage::disk($payment->receipt_disk)->exists($payment->receipt_path)
        ) {
            $receipts->queue($payment, force: true);

            return back()->with('success', 'Your receipt is being prepared. This page will update when it is ready.');
        }

        return Storage::disk($payment->receipt_disk)->download(
            $payment->receipt_path,
            'receipt-'.$payment->receipt_number.'.pdf',
            ['Content-Type' => 'application/pdf'],
        );
    }

    public function retryDuePaymentReceipt(
        Request $request,
        string $paystackTransactionId,
        DueReceiptService $receipts,
    ): RedirectResponse {
        $user = $this->authorizedUser($request);
        $payment = DuePayment::query()->where('paystack_transaction_id', $paystackTransactionId)->firstOrFail();
        abort_unless($this->userCanViewPayment($user, $payment), 404);
        $receipts->queue($payment, force: true);

        return back()->with('success', 'Receipt generation restarted. This page will update when it is ready.');
    }

    public function resendDuePaymentReceiptEmail(
        Request $request,
        string $paystackTransactionId,
        DueReceiptService $receipts,
    ): RedirectResponse {
        $user = $this->authorizedUser($request);
        $payment = DuePayment::query()->where('paystack_transaction_id', $paystackTransactionId)->firstOrFail();
        abort_unless($this->userCanViewPayment($user, $payment), 404);
        abort_unless($payment->receipt_status === DuePayment::ReceiptStatusReady, 404);
        $delivery = $receipts->queueEmail($payment, newSendCycle: true);

        return back()->with(
            $delivery instanceof EmailDelivery ? 'success' : 'error',
            $delivery instanceof EmailDelivery ? 'Receipt email queued.' : 'Add an email address before sending the receipt.',
        );
    }

    public function handlePaystackWebhook(Request $request, PaystackWebhookService $webhooks): Response
    {
        return response('', $webhooks->process($request->getContent(), $request->header('x-paystack-signature')));
    }

    public function createStaffPaymentAccount(Request $request): InertiaResponse
    {
        $advisor = $this->authorizedAdvisor($request);

        return $this->renderDashboard($request, 'payment-settings', overrides: [
            'staffPaymentAccountForm' => $this->paymentAccountFormPayload($advisor),
        ]);
    }

    public function storeStaffPaymentAccount(
        StoreStaffPaymentAccountRequest $request,
        PaystackService $paystack,
    ): RedirectResponse {
        $advisor = $this->authorizedAdvisor($request);
        $validated = $request->validated();
        $bank = $this->paystackBankFromCode($paystack, $validated['bank_code']);
        $current = PaymentAccount::currentFor($advisor) ?? PaymentAccount::latestFor($advisor);
        $description = sprintf('%s payout account for %s', $validated['label'], $advisor->user->name);

        try {
            $resolved = $paystack->resolveAccount($validated['account_number'], $validated['bank_code']);
            $subaccount = $current instanceof PaymentAccount
                && is_string($current->paystack_subaccount_code)
                && $current->paystack_subaccount_code !== ''
                && data_get($current->provider_payload, 'demo') !== true
                    ? $this->updateOrReplaceSubaccount($paystack, $current, $validated, $description)
                    : $paystack->createSubaccount(
                        $validated['business_name'],
                        $validated['bank_code'],
                        $validated['account_number'],
                        $description,
                    );
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'account_number' => 'Paystack could not confirm this account. Check the bank and account number, then try again.',
            ]);
        }

        $accountName = Arr::get($resolved, 'account_name');
        $subaccountCode = Arr::get($subaccount, 'subaccount_code');

        if (! is_string($accountName) || $accountName === '' || ! is_string($subaccountCode) || $subaccountCode === '') {
            throw ValidationException::withMessages([
                'account_number' => 'Paystack did not return enough account details. Check the account and try again.',
            ]);
        }

        DB::transaction(function () use ($advisor, $current, $validated, $bank, $accountName, $subaccountCode, $subaccount): void {
            $account = $current ?? new PaymentAccount;
            $account->forceFill([
                'advisor_profile_id' => $advisor->id,
                'provider' => PaymentAccount::ProviderPaystack,
                'label' => $validated['label'],
                'business_name' => $validated['business_name'],
                'bank_code' => $validated['bank_code'],
                'bank_name' => $bank['name'],
                'account_number' => $validated['account_number'],
                'account_number_last4' => Str::of($validated['account_number'])->substr(-4)->toString(),
                'account_name' => $accountName,
                'paystack_subaccount_code' => $subaccountCode,
                'status' => PaymentAccount::StatusActive,
                'verified_at' => now(),
                'activated_at' => $account->activated_at ?? now(),
                'disabled_at' => null,
                'provider_payload' => Arr::only($subaccount, [
                    'id', 'subaccount_code', 'domain', 'integration', 'managed_by_integration', 'active', 'is_verified', 'currency', 'createdAt', 'updatedAt',
                ]),
            ])->save();
            Due::query()->where('advisor_profile_id', $advisor->id)->update(['payment_account_id' => $account->id]);
            DuePayment::query()
                ->where('advisor_profile_id', $advisor->id)
                ->where('status', DuePayment::StatusInitialized)
                ->update([
                    'status' => DuePayment::StatusAbandoned,
                    'active_marker' => null,
                    'gateway_response' => 'Checkout was replaced after the payout account changed.',
                ]);
        });

        return to_route('dashboard.section', ['section' => 'payment-settings'])
            ->with('success', 'Payout account saved. New payments will use this account.');
    }

    public function updateStaffPassword(UpdateStaffPasswordRequest $request): RedirectResponse
    {
        $user = $this->authorizedUser($request);
        abort_unless($user->account_type === AccountType::Adviser, 403);
        $user->forceFill(['password' => $request->validated('password')])->save();

        return to_route('dashboard.section', ['section' => 'profile-settings'])
            ->with('success', 'Password changed.');
    }

    public function updateStudentProfile(UpdateStudentProfileRequest $request): RedirectResponse
    {
        $student = $this->authorizedStudent($request);
        $validated = $request->validated();
        $student->user->forceFill([
            'name' => Str::squish($validated['surname'].' '.$validated['other_names']),
        ])->save();

        return to_route('dashboard.section', ['section' => 'profile-settings'])
            ->with('success', 'Profile saved.');
    }

    public function updateProfilePhoto(UpdateProfilePhotoRequest $request): RedirectResponse
    {
        $user = $this->authorizedUser($request);
        $disk = User::profilePhotoDisk();
        $previous = $user->profile_photo_path;
        $path = $request->file('profile_photo')->store('profile-photos', $disk);
        $user->forceFill(['profile_photo_path' => $path])->save();

        if (is_string($previous) && $previous !== '') {
            Storage::disk($disk)->delete($previous);
        }

        return back()->with('success', 'Profile photo updated.');
    }

    /** @param array<string, mixed> $overrides */
    private function renderDashboard(
        Request $request,
        string $section,
        ?string $levelSlug = null,
        array $overrides = [],
    ): InertiaResponse {
        $user = $this->authorizedUser($request);
        $role = $this->dashboardRole($user);
        abort_unless(in_array($section, self::ROLE_SECTIONS[$role], true), 404);
        $advisor = $this->advisorForUser($user);
        $student = $user->studentProfile;
        $levels = $this->levelsFor($advisor);
        $selectedLevel = $levelSlug === null ? null : $levels->firstWhere('slug', $levelSlug);
        abort_if($levelSlug !== null && ! $selectedLevel instanceof AcademicYear, 404);
        $sets = $advisor->studentSets()->with('entryAcademicSession')->orderByDesc('entry_academic_session_id')->get();
        $setPayloads = $sets->map(fn (StudentSet $set): array => $this->studentSetPayload($set))->values()->all();

        $dashboard = [
            'role' => $role,
            'roleLabel' => $role === 'staff' ? 'Course Adviser' : 'Student',
            'section' => $section,
            'sectionTitle' => $selectedLevel instanceof AcademicYear ? $selectedLevel->name.' Results' : self::SECTION_TITLES[$section],
            'levelSlug' => $selectedLevel?->slug,
            'level' => $selectedLevel?->name,
            'levels' => $levels->map(fn (AcademicYear $level): array => $this->levelPayload($level))->all(),
            'courseScheduleLevels' => $levels->map(fn (AcademicYear $level): array => $this->levelPayload($level))->all(),
            'advisedSets' => $setPayloads,
            'programme' => $this->programmePayload($advisor),
            'staffHome' => $role === 'staff' && $section === 'home' ? $this->advisorHomePayload($advisor, $sets, $levels) : null,
            'staffStudents' => $role === 'staff' && $section === 'students' ? $this->advisorStudentsPayload($advisor, $sets) : null,
            'staffStudentForm' => null,
            'staffCourseSchedules' => $role === 'staff' && $section === 'courses' ? $this->courseOfferingsPayload($request, $advisor, $sets, $levels) : null,
            'staffCourseOfferingForm' => null,
            'staffCourseRegistration' => $role === 'staff' && $section === 'course-registration' ? $this->staffCourseRegistrationPayload($advisor) : null,
            'staffResults' => $role === 'staff' && $section === 'result-management' ? $this->advisorResultsPayload($advisor, $sets) : null,
            'staffResultImportForm' => null,
            'staffDues' => $role === 'staff' && $section === 'dues' ? $this->advisorDuesPayload($advisor, $sets) : null,
            'staffDueForm' => null,
            'staffDueDetail' => null,
            'staffDueWaiverForm' => null,
            'staffPaymentSettings' => $role === 'staff' && $section === 'payment-settings' ? $this->paymentSettingsPayload($advisor) : null,
            'staffPaymentAccountForm' => null,
            'staffSettings' => $role === 'staff' && $section === 'profile-settings' ? $this->advisorSettingsPayload($advisor, $sets) : null,
            'resultSheetDetail' => null,
            'studentRecord' => null,
            'student' => $student instanceof StudentProfile ? $this->studentHeaderPayload($student) : null,
            'studentProfile' => $role === 'student' && $section === 'profile-settings' ? $this->studentProfilePayload($student) : null,
            'studentHome' => $role === 'student' && $section === 'home' ? $this->studentHomePayload($student) : null,
            'studentResults' => $role === 'student' && $section === 'results' ? $this->studentResultsPayload($student, $selectedLevel) : null,
            'studentDues' => $role === 'student' && $section === 'dues' ? $this->studentDuesPayload($student) : null,
            'studentCourseRegistration' => $role === 'student' && $section === 'course-registration' ? $this->studentCourseRegistrationPayload($student) : null,
        ];

        return Inertia::render('dashboard', ['dashboard' => array_replace($dashboard, $overrides)]);
    }

    private function advisorHomePayload(AdvisorProfile $advisor, Collection $sets, Collection $levels): array
    {
        $students = $advisor->students()->with(['user', 'currentLevel', 'studentSet.entryAcademicSession'])->get();
        $summaries = $this->academicPerformance->tableSummariesFor($students, (int) $advisor->minimum_years_to_degree);
        $payments = DuePayment::query()
            ->with(['studentProfile.user', 'assignment.due'])
            ->where('advisor_profile_id', $advisor->id)
            ->where('status', DuePayment::StatusSuccess)
            ->latest('paid_at')
            ->limit(8)
            ->get();

        return [
            'assignedSets' => $sets->map(fn (StudentSet $set): array => $this->studentSetPayload($set))->values()->all(),
            'levels' => $levels->map(fn (AcademicYear $level): array => $this->levelPayload($level))->all(),
            'students' => $students->map(fn (StudentProfile $student): array => $this->staffStudentPayload($student, $summaries->get($student->id, [])))->all(),
            'totalStudents' => $students->count(),
            'maximumCourseUnits' => (int) $advisor->maximum_course_units,
            'studentsWithCarryovers' => $summaries->filter(fn (array $summary): bool => ($summary['pendingCarryovers'] ?? []) !== [])->count(),
            'pendingDuesByStudent' => [],
            'recentPayments' => $payments->map(fn (DuePayment $payment): array => [
                'studentName' => $payment->studentProfile->user->name,
                'studentAvatar' => $payment->studentProfile->user->profilePhotoUrl(),
                'dueName' => $payment->assignment->due->title,
                'amount' => $this->formatAmount((int) $payment->amount_kobo),
                'paidAt' => $payment->paid_at?->toFormattedDateString() ?? 'Paid',
            ])->all(),
            'carryoverSummaries' => [],
        ];
    }

    private function advisorStudentsPayload(AdvisorProfile $advisor, Collection $sets): array
    {
        $students = $advisor->students()
            ->with(['user', 'currentLevel', 'studentSet.entryAcademicSession'])
            ->get()
            ->sortBy(fn (StudentProfile $student): string => $student->user->name)
            ->values();
        $summaries = $this->academicPerformance->tableSummariesFor($students, (int) $advisor->minimum_years_to_degree);

        return [
            'programme' => $this->programmePayload($advisor),
            'assignedSets' => $sets->map(fn (StudentSet $set): array => $this->studentSetPayload($set))->values()->all(),
            'totalStudents' => $students->count(),
            'students' => $students->map(fn (StudentProfile $student): array => $this->staffStudentPayload($student, $summaries->get($student->id, [])))->all(),
        ];
    }

    private function studentFormPayload(AdvisorProfile $advisor, string $mode): array
    {
        $sets = $advisor->studentSets()->with('entryAcademicSession')->orderByDesc('entry_academic_session_id')->get();

        return [
            'mode' => $mode,
            'programme' => $this->programmePayload($advisor),
            'assignedSets' => $sets->map(fn (StudentSet $set): array => $this->studentSetPayload($set))->values()->all(),
            'suggestedAccessCode' => $this->generateAccessCode(),
        ];
    }

    /** @param array<string, mixed> $summary */
    private function staffStudentPayload(StudentProfile $student, array $summary = []): array
    {
        $student->loadMissing(['user', 'currentLevel', 'studentSet.entryAcademicSession', 'advisorProfile.user']);

        return [
            'id' => (int) $student->id,
            'fullName' => $student->user->name,
            'email' => $student->user->email,
            'registrationNumber' => $student->registration_number,
            'avatar' => $student->user->profilePhotoUrl(),
            'currentLevel' => $student->currentLevel instanceof AcademicYear ? $this->levelPayload($student->currentLevel) : null,
            'studentSet' => $this->studentSetPayload($student->studentSet),
            'entrySession' => $this->academicSessionPayload($student->studentSet->entryAcademicSession),
            'advisorName' => $student->advisorProfile->user->name,
            'pendingCarryovers' => $summary['pendingCarryovers'] ?? [],
            'levelGpas' => collect($summary['levelGpas'] ?? [])->map(fn (array $item): array => [
                'level' => $item['level']->name,
                'gpa' => $item['gpa'],
            ])->all(),
            'cgpa' => $summary['cgpa'] ?? null,
            'profileUrl' => route('dashboard.students.show', ['registrationNumber' => $student->registration_number]),
            'accountStatus' => $student->user->activated_at === null ? 'Pending signup' : 'Active',
            'accessCode' => $student->user->activated_at === null ? $student->user->access_code : null,
        ];
    }

    private function courseOfferingsPayload(Request $request, AdvisorProfile $advisor, Collection $sets, Collection $levels): array
    {
        $selectedSet = $sets->firstWhere('id', $request->integer('set')) ?? $sets->first();

        if (! $selectedSet instanceof StudentSet) {
            return [
                'level' => null,
                'levels' => $levels->map(fn (AcademicYear $level): array => $this->levelPayload($level))->all(),
                'academicSession' => null,
                'selectedSet' => null,
                'assignedSets' => [],
                'semesters' => [],
            ];
        }

        $level = $levels->firstWhere('slug', $request->string('level')->toString())
            ?? $this->academicContext->academicYearForSet($selectedSet)
            ?? $levels->first();
        $session = $level instanceof AcademicYear ? $this->academicContext->sessionForSetYear($selectedSet, $level) : null;

        return [
            'level' => $level instanceof AcademicYear ? $this->levelPayload($level) : null,
            'levels' => $levels->map(fn (AcademicYear $item): array => $this->levelPayload($item))->all(),
            'academicSession' => $session instanceof AcademicSession ? $this->academicSessionPayload($session) : null,
            'selectedSet' => $this->studentSetPayload($selectedSet),
            'assignedSets' => $sets->map(fn (StudentSet $set): array => $this->studentSetPayload($set))->values()->all(),
            'semesters' => $level instanceof AcademicYear && $session instanceof AcademicSession
                ? Semester::query()->orderBy('sort_order')->get()->map(
                    fn (Semester $semester): array => $this->courseSemesterPayload($selectedSet, $session, $level, $semester),
                )->all()
                : [],
        ];
    }

    private function courseSemesterPayload(StudentSet $set, AcademicSession $session, AcademicYear $level, Semester $semester): array
    {
        $slot = $this->yearSemester($level, $semester);
        $offerings = CourseOffering::query()
            ->with(['course', 'prerequisiteCourses'])
            ->where('student_set_id', $set->id)
            ->where('academic_session_id', $session->id)
            ->where('academic_year_semester_id', $slot->id)
            ->whereNull('archived_at')
            ->get()
            ->sortBy(fn (CourseOffering $offering): string => $offering->course->code)
            ->values();

        return [
            'id' => $semester->id,
            'name' => $semester->name,
            'slug' => $semester->slug,
            'totalUnits' => (int) $offerings->sum('credit_units'),
            'facultyCourseCount' => $offerings->where('category', CourseCategory::Faculty)->count(),
            'departmentalCourseCount' => $offerings->where('category', CourseCategory::Departmental)->count(),
            'electiveCourseCount' => $offerings->where('category', CourseCategory::Elective)->count(),
            'offerings' => $offerings->map(fn (CourseOffering $offering): array => [
                'id' => (int) $offering->id,
                'courseId' => (int) $offering->course_id,
                'code' => $offering->course->code,
                'title' => $offering->course_title,
                'units' => (int) $offering->credit_units,
                'category' => $offering->category->value,
                'prerequisites' => $offering->prerequisiteCourses->map(fn (Course $course): array => [
                    'id' => (int) $course->id, 'code' => $course->code, 'title' => $course->title,
                ])->all(),
                'canEdit' => true,
            ])->all(),
        ];
    }

    private function courseOfferingFormPayload(
        AdvisorProfile $advisor,
        StudentSet $set,
        AcademicSession $session,
        AcademicYear $level,
        Semester $semester,
        ?CourseOffering $offering = null,
    ): array {
        $target = $this->yearSemester($level, $semester);
        $previous = CourseOffering::query()
            ->with(['course', 'academicYearSemester.academicYear', 'academicYearSemester.semester'])
            ->where('student_set_id', $set->id)
            ->whereNull('archived_at')
            ->where(function ($query) use ($session, $target): void {
                $query->where('academic_session_id', '<', $session->id)
                    ->orWhere(fn ($query) => $query->where('academic_session_id', $session->id)->where('academic_year_semester_id', '<', $target->id));
            })
            ->get()
            ->unique('course_id')
            ->sortBy(fn (CourseOffering $item): string => sprintf('%02d-%02d-%s', $item->academicYearSemester->academicYear->year_number, $item->academicYearSemester->semester->sort_order, $item->course->code))
            ->values();
        $offering?->loadMissing(['course', 'prerequisiteCourses']);

        return [
            'mode' => $offering instanceof CourseOffering ? 'edit' : 'create',
            'academicSession' => $this->academicSessionPayload($session),
            'level' => $this->levelPayload($level),
            'semester' => $this->semesterPayload($semester),
            'offering' => $offering instanceof CourseOffering ? [
                'id' => (int) $offering->id,
                'code' => $offering->course->code,
                'title' => $offering->course_title,
                'creditUnits' => (int) $offering->credit_units,
                'category' => $offering->category->value,
                'prerequisiteCourseIds' => $offering->prerequisiteCourses->pluck('id')->map(fn ($id): int => (int) $id)->all(),
            ] : null,
            'prefill' => null,
            'studentSetId' => (int) $set->id,
            'returnTo' => null,
            'prerequisiteOptions' => $previous->map(fn (CourseOffering $item): array => [
                'id' => (int) $item->course_id,
                'code' => $item->course->code,
                'title' => $item->course_title,
                'units' => (int) $item->credit_units,
                'level' => $item->academicYearSemester->academicYear->name,
                'semester' => $item->academicYearSemester->semester->name,
            ])->all(),
        ];
    }

    private function advisorResultsPayload(AdvisorProfile $advisor, Collection $sets): array
    {
        $sheets = $advisor->resultSheets()->with($this->resultSheetRelations())->latest('updated_at')->get();

        return [
            'programme' => $this->programmePayload($advisor),
            'assignedSets' => $sets->map(fn (StudentSet $set): array => $this->studentSetPayload($set))->values()->all(),
            'resultSheets' => $sheets->map(fn (ResultSheet $sheet): array => $this->resultSheetPayload($sheet))->all(),
            'importUrl' => route('dashboard.results.import'),
        ];
    }

    private function resultImportFormPayload(AdvisorProfile $advisor): array
    {
        $sets = $advisor->studentSets()->with('entryAcademicSession')->orderByDesc('entry_academic_session_id')->get();
        $levels = $this->levelsFor($advisor);
        $slots = AcademicYearSemester::query()
            ->with(['academicYear', 'semester'])
            ->whereIn('academic_year_id', $levels->pluck('id'))
            ->get()
            ->sortBy(fn (AcademicYearSemester $slot): string => sprintf('%02d-%02d', $slot->academicYear->year_number, $slot->semester->sort_order))
            ->values();
        $offerings = CourseOffering::query()
            ->with(['course', 'academicSession', 'academicYearSemester.academicYear', 'academicYearSemester.semester'])
            ->whereIn('student_set_id', $sets->pluck('id'))
            ->whereNull('archived_at')
            ->get();

        return [
            'programme' => $this->programmePayload($advisor),
            'academicSessions' => $advisor->academicSessions()->oldest('starts_on')->get()->map(fn (AcademicSession $session): array => $this->academicSessionPayload($session))->all(),
            'assignedSets' => $sets->map(fn (StudentSet $set): array => $this->studentSetPayload($set))->values()->all(),
            'yearSemesters' => $slots->map(fn (AcademicYearSemester $slot): array => [
                'id' => (int) $slot->id,
                'level' => $this->levelPayload($slot->academicYear),
                'semester' => $this->semesterPayload($slot->semester),
                'label' => $this->qualifiedSemester($slot),
            ])->all(),
            'knownCourses' => $advisor->courses()->orderBy('code')->get()->map(fn (Course $course): array => [
                'code' => $course->code, 'title' => $course->title, 'creditUnits' => (int) $course->credit_units,
            ])->all(),
            'courseOfferings' => $offerings->map(fn (CourseOffering $offering): array => [
                'id' => (int) $offering->id,
                'studentSetId' => (int) $offering->student_set_id,
                'academicSessionId' => (int) $offering->academic_session_id,
                'academicYearSemesterId' => (int) $offering->academic_year_semester_id,
                'level' => $this->levelPayload($offering->academicYearSemester->academicYear),
                'semester' => $this->semesterPayload($offering->academicYearSemester->semester),
                'label' => $this->qualifiedSemester($offering->academicYearSemester),
                'code' => $offering->course->code,
                'title' => $offering->course_title,
                'creditUnits' => (int) $offering->credit_units,
            ])->all(),
            'defaults' => [
                'studentSetId' => $sets->first()?->id,
                'academicYearSemesterId' => $slots->first()?->id,
            ],
        ];
    }

    private function resultSheetDetailPayload(ResultSheet $sheet, User $viewer): array
    {
        $sheet->loadMissing($this->resultSheetRelations());
        $source = $sheet->revisions->whereNotNull('source_file_path')->sortByDesc('revision_number')->first();

        return [
            'sheet' => $this->resultSheetPayload($sheet),
            'mode' => 'view',
            'canReview' => false,
            'canEdit' => $viewer->account_type === AccountType::Adviser && $sheet->status === ResultSheet::StatusDraft,
            'revisionNumber' => (int) $sheet->current_revision_number,
            'sourceFileUrl' => $source === null ? null : route('dashboard.results.source-file', ['resultSheet' => $sheet]),
            'returnSection' => $viewer->account_type === AccountType::Adviser ? 'result-management' : 'results',
            'returnLabel' => $viewer->account_type === AccountType::Adviser ? 'Back to Result Management' : 'Back to Results',
        ];
    }

    private function resultSheetPayload(ResultSheet $sheet): array
    {
        $sheet->loadMissing($this->resultSheetRelations());
        $courses = $sheet->courses->values();
        $rows = $sheet->studentResults->sortBy(fn (StudentSemesterResult $result): string => $result->studentProfile->user->name)->values();

        return [
            'id' => (int) $sheet->id,
            'status' => $sheet->status,
            'statusLabel' => $sheet->status === ResultSheet::StatusPublished ? 'Published' : 'Draft',
            'correctionNote' => null,
            'academicSession' => $this->academicSessionPayload($sheet->academicSession),
            'entrySession' => $this->academicSessionPayload($sheet->studentSet->entryAcademicSession),
            'level' => $this->levelPayload($sheet->academicYearSemester->academicYear),
            'semester' => $this->semesterPayload($sheet->academicYearSemester->semester),
            'submittedBy' => $sheet->createdBy instanceof User ? [
                'name' => $sheet->createdBy->name,
                'email' => $sheet->createdBy->email,
                'avatar' => $sheet->createdBy->profilePhotoUrl(),
                'profileUrl' => null,
            ] : null,
            'submittedAt' => $sheet->updated_at?->toFormattedDateString(),
            'approvedAt' => $sheet->published_at?->toFormattedDateString(),
            'studentCount' => $rows->count(),
            'courseCount' => $courses->count(),
            'averageGpa' => number_format((float) ($rows->average('gpa') ?? 0), 2),
            'courses' => $courses->map(fn (ResultSheetCourse $course): array => [
                'id' => (int) $course->id,
                'code' => $course->course_code,
                'title' => $course->course_title,
                'creditUnits' => (int) $course->credit_units,
                'isCarryover' => (int) $course->courseOffering->academic_year_semester_id !== (int) $sheet->academic_year_semester_id,
            ])->all(),
            'rows' => $rows->values()->map(function (StudentSemesterResult $result, int $index) use ($courses): array {
                $grades = $result->courseResults->keyBy('result_sheet_course_id');

                return [
                    'id' => (int) $result->id,
                    'serialNumber' => $index + 1,
                    'student' => [
                        'name' => $result->studentProfile->user->name,
                        'avatar' => $result->studentProfile->user->profilePhotoUrl(),
                        'registrationNumber' => $result->studentProfile->registration_number,
                    ],
                    'grades' => $courses->map(function (ResultSheetCourse $course) use ($grades): array {
                        $grade = $grades->get($course->id);

                        return [
                            'courseId' => (int) $course->id,
                            'grade' => $grade?->grade,
                            'gradePoint' => $grade?->grade_point,
                            'qualityPoints' => $grade?->quality_points,
                        ];
                    })->all(),
                    'totalUnits' => (int) $result->total_units,
                    'gpa' => number_format((float) $result->gpa, 2),
                ];
            })->all(),
        ];
    }

    private function studentResultsPayload(StudentProfile $student, ?AcademicYear $level): array
    {
        $level ??= $student->currentLevel ?? $this->levelsFor($student->advisorProfile)->first();
        abort_unless($level instanceof AcademicYear, 404);
        $results = $this->academicPerformance->publishedSemesterResults($student)
            ->filter(fn (StudentSemesterResult $result): bool => (int) $result->resultSheet->academicYearSemester->academic_year_id === (int) $level->id)
            ->keyBy(fn (StudentSemesterResult $result): string => $result->resultSheet->academicYearSemester->semester->slug);
        $semesters = Semester::query()->orderBy('sort_order')->get()->map(function (Semester $semester) use ($results): array {
            $result = $results->get($semester->slug);

            return [
                'name' => $semester->name,
                'slug' => $semester->slug,
                'gpa' => $result instanceof StudentSemesterResult ? number_format((float) $result->gpa, 2) : null,
                'totalUnits' => $result instanceof StudentSemesterResult ? (int) $result->total_units : 0,
                'courses' => $result instanceof StudentSemesterResult
                    ? $result->courseResults->sortBy(fn ($grade): int => $grade->resultSheetCourse->sort_order)->map(fn ($grade): array => [
                        'code' => $grade->resultSheetCourse->course_code,
                        'title' => $grade->resultSheetCourse->course_title,
                        'units' => (int) $grade->resultSheetCourse->credit_units,
                        'grade' => $grade->grade,
                    ])->values()->all()
                    : [],
            ];
        });
        $allCourses = $semesters->flatMap(fn (array $semester): array => $semester['courses']);
        $totalUnits = (int) $semesters->sum('totalUnits');
        $gradePoints = (int) $results->sum('total_grade_points');

        return [
            'level' => $this->levelPayload($level),
            'averageGpa' => $totalUnits > 0 ? number_format($gradePoints / $totalUnits, 2) : null,
            'totalUnits' => $totalUnits,
            'failedCourses' => $allCourses->where('grade', 'F')->count(),
            'totalCourses' => $allCourses->count(),
            'semesters' => $semesters->all(),
        ];
    }

    private function studentHomePayload(StudentProfile $student): array
    {
        $summary = $this->academicPerformance->summary($student);
        $current = $this->currentRegistrationContextOrNull($student);
        $registration = $current === null ? null : CourseRegistration::query()
            ->with('items')
            ->where('student_profile_id', $student->id)
            ->where('academic_session_id', $current[0]->id)
            ->where('academic_year_semester_id', $current[1]->id)
            ->first();
        $courses = $registration?->items ?? collect();
        $pendingDues = $student->dueAssignments()
            ->with('due')
            ->where('status', StudentDueAssignment::StatusPending)
            ->whereHas('due', fn ($query) => $query->where('status', Due::StatusPublished))
            ->get();
        $carryovers = $this->academicPerformance->carryovers($student);

        return [
            'cgpa' => $summary['cgpa'],
            'currentSemester' => $current === null ? null : [
                'name' => $current[1]->semester->name,
                'level' => $current[1]->academicYear->name,
                'session' => $current[0]->name,
            ],
            'courseAdvisor' => $student->advisorProfile->user->name,
            'totalCourseUnits' => (int) $courses->sum('credit_units'),
            'levels' => $this->levelsFor($student->advisorProfile)->map(fn (AcademicYear $item): array => $this->levelPayload($item))->all(),
            'gpaProgress' => $this->academicPerformance->gpaByAcademicYear($student, (int) $student->studentSet->minimum_years_to_degree)->map(fn (array $item): array => [
                'level' => $item['level']->name,
                'average' => $item['gpa'] === null ? null : (float) $item['gpa'],
            ])->all(),
            'courses' => $courses->map(fn (CourseRegistrationItem $item): array => [
                'code' => $item->course_code,
                'title' => $item->course_title,
                'units' => (int) $item->credit_units,
                'type' => $this->courseCategoryLabel($item->category),
            ])->all(),
            'pendingDues' => $pendingDues->map(fn (StudentDueAssignment $assignment): array => [
                'title' => $assignment->due->title,
                'context' => $student->studentSet->name,
                'amount' => $this->formatAmount((int) $assignment->amount_kobo),
                'dueDate' => $assignment->due->due_on?->toFormattedDateString() ?? 'No due date',
            ])->all(),
            'resits' => $carryovers->map(fn (array $carryover): array => [
                'code' => $carryover['code'],
                'title' => $carryover['title'],
                'units' => $carryover['units'],
                'originalSemester' => $carryover['originalSemester'],
                'resitWindow' => 'Available during course registration',
                'status' => 'Pending',
            ])->all(),
        ];
    }

    private function studentCourseRegistrationPayload(StudentProfile $student): array
    {
        [$session, $slot] = $this->currentRegistrationContext($student);
        $plan = app(CourseRegistrationPlanner::class)->plan($student, $session, $slot);
        $registration = CourseRegistration::query()
            ->with('items')
            ->where('student_profile_id', $student->id)
            ->where('academic_session_id', $session->id)
            ->where('academic_year_semester_id', $slot->id)
            ->first();
        $history = $student->courseRegistrations()
            ->with(['items', 'academicSession', 'academicYearSemester.academicYear', 'academicYearSemester.semester'])
            ->when($registration instanceof CourseRegistration, fn ($query) => $query->whereKeyNot($registration->id))
            ->latest('submitted_at')
            ->get();

        return [
            'currentSemester' => ['name' => $slot->semester->name, 'level' => $slot->academicYear->name, 'session' => $session->name],
            'maximumCourseUnits' => (int) $student->studentSet->maximum_course_units,
            'advisorName' => $student->advisorProfile->user->name,
            'reviewStatus' => $registration === null ? 'Not submitted' : $this->registrationStatusLabel($registration->status),
            'feedbackNote' => $registration?->feedback_note,
            'registrationId' => $registration?->id,
            'selectedUnits' => (int) ($registration?->items->sum('credit_units') ?? 0),
            'selectedCourses' => $registration?->items->map(fn (CourseRegistrationItem $item): array => $this->registrationItemPayload($item))->all() ?? [],
            'availableCourses' => $plan['availableCourses'],
            'selectableCourses' => $plan['selectableCourses'],
            'courseGroups' => $plan['courseGroups'],
            'previousRegistrations' => $history->map(fn (CourseRegistration $item): array => $this->registrationSummaryPayload($item, false))->all(),
        ];
    }

    private function staffCourseRegistrationPayload(AdvisorProfile $advisor, ?CourseRegistration $review = null): array
    {
        $sets = $advisor->studentSets()->with('entryAcademicSession')->orderByDesc('entry_academic_session_id')->get();
        $registrations = CourseRegistration::query()
            ->with(['items', 'studentProfile.user', 'studentSet.entryAcademicSession', 'academicSession', 'academicYearSemester.academicYear', 'academicYearSemester.semester'])
            ->where('advisor_profile_id', $advisor->id)
            ->where('status', CourseRegistration::StatusSubmitted)
            ->latest('submitted_at')
            ->get();

        return [
            'assignedSets' => $sets->map(fn (StudentSet $set): array => $this->studentSetPayload($set))->all(),
            'registrations' => $registrations->map(fn (CourseRegistration $item): array => $this->registrationSummaryPayload($item, true))->all(),
            'review' => $review instanceof CourseRegistration ? $this->registrationReviewPayload($review) : null,
        ];
    }

    private function registrationSummaryPayload(CourseRegistration $registration, bool $includeStudent): array
    {
        $registration->loadMissing(['items', 'studentProfile.user', 'academicSession', 'academicYearSemester.academicYear', 'academicYearSemester.semester']);
        $payload = [
            'id' => (int) $registration->id,
            'status' => $this->registrationStatusLabel($registration->status),
            'semester' => [
                'level' => $registration->academicYearSemester->academicYear->name,
                'name' => $registration->academicYearSemester->semester->name,
                'session' => $registration->academicSession->name,
            ],
            'totalUnits' => (int) $registration->items->sum('credit_units'),
            'courses' => $registration->items->map(fn (CourseRegistrationItem $item): array => $this->registrationItemPayload($item))->all(),
            'reviewUrl' => route('dashboard.course-registration.show', ['courseRegistration' => $registration]),
        ];

        if ($includeStudent) {
            $payload['student'] = [
                'name' => $registration->studentProfile->user->name,
                'avatar' => $registration->studentProfile->user->profilePhotoUrl(),
                'registrationNumber' => $registration->studentProfile->registration_number,
                'profileUrl' => route('dashboard.students.show', ['registrationNumber' => $registration->studentProfile->registration_number]),
            ];
            $payload['submittedAt'] = $registration->submitted_at?->toFormattedDateString();
        }

        return $payload;
    }

    private function registrationReviewPayload(CourseRegistration $registration): array
    {
        $registration->loadMissing(['advisorProfile', 'studentProfile.user', 'studentSet.entryAcademicSession', 'items']);
        $summary = $this->academicPerformance->summary($registration->studentProfile);
        $base = $this->registrationSummaryPayload($registration, true);

        return [
            ...$base,
            'student' => [...$base['student'], 'cgpa' => $summary['cgpa'] ?? 'Not available'],
            'set' => $this->studentSetPayload($registration->studentSet),
            'programme' => $this->programmePayload($registration->advisorProfile),
            'regularUnits' => (int) $registration->items->whereIn('source', [CourseRegistrationItem::SourceCurrentSet, CourseRegistrationItem::SourceElective])->sum('credit_units'),
            'carryoverUnits' => (int) $registration->items->whereIn('source', [CourseRegistrationItem::SourceCarryover, CourseRegistrationItem::SourceOutstandingRequirement])->sum('credit_units'),
            'feedbackNote' => $registration->feedback_note,
            'canReview' => $registration->status === CourseRegistration::StatusSubmitted,
        ];
    }

    private function advisorDuesPayload(AdvisorProfile $advisor, Collection $sets): array
    {
        $dues = $advisor->dues()
            ->with(['studentSet.entryAcademicSession', 'assignments.latestPayment', 'paymentAccount'])
            ->latest()
            ->get();
        $assignments = $dues->where('status', Due::StatusPublished)->flatMap(fn (Due $due): Collection => $due->assignments);
        $pending = $assignments->where('status', StudentDueAssignment::StatusPending);
        $paid = $assignments->where('status', StudentDueAssignment::StatusPaid);

        return [
            'assignedSets' => $sets->map(fn (StudentSet $set): array => $this->studentSetPayload($set))->all(),
            'metrics' => [
                'expectedAmount' => $this->formatAmount((int) $assignments->sum('amount_kobo')),
                'collectedAmount' => $this->formatAmount((int) $paid->sum('amount_kobo')),
                'outstandingAmount' => $this->formatAmount((int) $pending->sum('amount_kobo')),
                'studentsOwing' => $pending->pluck('student_profile_id')->unique()->count(),
            ],
            'dues' => $dues->map(fn (Due $due): array => $this->dueSummaryPayload($due))->all(),
            'createUrl' => route('dashboard.dues.create'),
            'paymentSettingsUrl' => route('dashboard.section', ['section' => 'payment-settings']),
            'hasActivePaymentAccount' => PaymentAccount::currentFor($advisor) instanceof PaymentAccount,
        ];
    }

    private function staffDueFormPayload(AdvisorProfile $advisor, ?Due $due = null): array
    {
        $sets = $advisor->studentSets()->with('entryAcademicSession')->orderByDesc('entry_academic_session_id')->get();
        $account = PaymentAccount::currentFor($advisor) ?? PaymentAccount::latestFor($advisor);

        return [
            'mode' => $due instanceof Due ? 'edit' : 'create',
            'assignedSets' => $sets->map(fn (StudentSet $set): array => $this->studentSetPayload($set))->all(),
            'paymentAccounts' => $account instanceof PaymentAccount ? [$this->paymentAccountPayload($account)] : [],
            'paymentSettingsUrl' => route('dashboard.section', ['section' => 'payment-settings']),
            'defaults' => [
                'studentSetId' => $due?->student_set_id ?? $sets->first()?->id,
                'title' => $due?->title ?? '',
                'description' => $due?->description ?? '',
                'amountNaira' => $due instanceof Due ? (string) ((int) $due->amount_kobo / 100) : '',
                'dueOn' => $due?->due_on?->toDateString() ?? now()->addMonth()->toDateString(),
            ],
            'due' => $due instanceof Due ? [
                'id' => (int) $due->id,
                'status' => $this->dueStatusLabel($due->status),
                'canChangeSet' => $due->status === Due::StatusDraft,
                'canChangeAmount' => $due->status !== Due::StatusArchived,
            ] : null,
        ];
    }

    private function staffDueDetailPayload(Due $due): array
    {
        $due->loadMissing(['studentSet.entryAcademicSession', 'paymentAccount', 'assignments.studentProfile.user', 'assignments.latestPayment.receiptEmailDelivery']);

        return [
            'due' => $this->dueSummaryPayload($due),
            'students' => $due->assignments
                ->sortBy(fn (StudentDueAssignment $assignment): string => $assignment->studentProfile->user->name)
                ->values()
                ->map(fn (StudentDueAssignment $assignment): array => $this->dueStudentPayload($assignment))
                ->all(),
        ];
    }

    private function staffDueWaiverFormPayload(Due $due, StudentDueAssignment $assignment): array
    {
        $assignment->loadMissing('studentProfile.user');

        return [
            'due' => ['id' => (int) $due->id, 'title' => $due->title, 'amount' => $this->formatAmount((int) $assignment->amount_kobo)],
            'assignment' => [
                'id' => (int) $assignment->id,
                'studentName' => $assignment->studentProfile->user->name,
                'studentAvatar' => $assignment->studentProfile->user->profilePhotoUrl(),
                'registrationNumber' => $assignment->studentProfile->registration_number,
            ],
        ];
    }

    private function dueSummaryPayload(Due $due): array
    {
        $due->loadMissing(['studentSet.entryAcademicSession', 'assignments', 'paymentAccount']);
        $paid = $due->assignments->where('status', StudentDueAssignment::StatusPaid);
        $pending = $due->assignments->where('status', StudentDueAssignment::StatusPending);
        $waived = $due->assignments->where('status', StudentDueAssignment::StatusWaived);

        return [
            'id' => (int) $due->id,
            'title' => $due->title,
            'description' => $due->description,
            'amount' => $this->formatAmount((int) $due->amount_kobo),
            'status' => $this->dueStatusLabel($due->status),
            'set' => $due->studentSet->name,
            'dueDate' => $due->due_on?->toFormattedDateString(),
            'paidCount' => $paid->count(),
            'pendingCount' => $pending->count(),
            'waivedCount' => $waived->count(),
            'collectedAmount' => $this->formatAmount((int) $paid->sum('amount_kobo')),
            'outstandingAmount' => $this->formatAmount((int) $pending->sum('amount_kobo')),
            'paymentAccount' => $due->paymentAccount instanceof PaymentAccount ? $this->paymentAccountPayload($due->paymentAccount) : null,
            'detailUrl' => route('dashboard.dues.show', ['due' => $due]),
        ];
    }

    private function dueStudentPayload(StudentDueAssignment $assignment): array
    {
        $payment = $assignment->latestPayment;

        if ($payment instanceof DuePayment && $payment->receipt_status === null && $payment->paystack_transaction_id !== null) {
            $payment = $this->dueReceipts->queue($payment);
        }

        return [
            'id' => (int) $assignment->id,
            'studentName' => $assignment->studentProfile->user->name,
            'studentAvatar' => $assignment->studentProfile->user->profilePhotoUrl(),
            'registrationNumber' => $assignment->studentProfile->registration_number,
            'amount' => $this->formatAmount((int) $assignment->amount_kobo),
            'status' => match ($assignment->status) {
                StudentDueAssignment::StatusPaid => 'Paid',
                StudentDueAssignment::StatusWaived => 'Waived',
                default => 'Pending',
            },
            'paidAt' => $assignment->paid_at?->toFormattedDateString(),
            'waiverReason' => $assignment->waiver_reason,
            ...$this->receiptPayload($payment),
        ];
    }

    private function studentDuesPayload(StudentProfile $student): array
    {
        $assignments = $student->dueAssignments()
            ->with(['due.paymentAccount', 'latestPayment.receiptEmailDelivery', 'payments'])
            ->whereHas('due', fn ($query) => $query->where('status', Due::StatusPublished))
            ->latest()
            ->get();
        $pending = $assignments->where('status', StudentDueAssignment::StatusPending);
        $paid = $assignments->where('status', StudentDueAssignment::StatusPaid);

        return [
            'pendingAmount' => $this->formatAmount((int) $pending->sum('amount_kobo')),
            'paidAmount' => $this->formatAmount((int) $paid->sum(fn (StudentDueAssignment $assignment): int => (int) ($assignment->latestPayment?->amount_kobo ?? $assignment->amount_kobo))),
            'totalDues' => $assignments->count(),
            'pending' => $pending->map(fn (StudentDueAssignment $assignment): array => $this->studentPendingDuePayload($assignment))->values()->all(),
            'paid' => $paid->map(fn (StudentDueAssignment $assignment): array => $this->studentPaidDuePayload($assignment))->values()->all(),
        ];
    }

    private function studentPendingDuePayload(StudentDueAssignment $assignment): array
    {
        $account = $assignment->due->paymentAccount;
        $canPay = $account?->isActive() === true;
        $hasCheckout = $canPay && $assignment->payments->contains(
            fn (DuePayment $payment): bool => $payment->status === DuePayment::StatusInitialized
                && is_string($payment->authorization_url)
                && $payment->authorization_url !== ''
                && $payment->created_at?->isAfter(now()->subMinutes(30)) === true
                && data_get($payment->provider_payload, 'payout_account.subaccount_code') === $account?->paystack_subaccount_code,
        );

        return [
            'id' => (int) $assignment->id,
            'name' => $assignment->due->title,
            'description' => $assignment->due->description,
            'amount' => $this->formatAmount((int) $assignment->amount_kobo),
            'dueDate' => $assignment->due->due_on?->toFormattedDateString(),
            'paymentUrl' => $canPay ? route('dashboard.dues.payments.store', ['studentDueAssignment' => $assignment]) : null,
            'paymentActionLabel' => $hasCheckout ? 'Continue payment' : 'Pay now',
            'paymentUnavailableReason' => $canPay ? null : 'Ask your course adviser to reconnect the payout account.',
        ];
    }

    private function studentPaidDuePayload(StudentDueAssignment $assignment): array
    {
        $payment = $assignment->latestPayment;

        if ($payment instanceof DuePayment && $payment->receipt_status === null && $payment->paystack_transaction_id !== null) {
            $payment = $this->dueReceipts->queue($payment);
        }

        return [
            'id' => (int) $assignment->id,
            'name' => $assignment->due->title,
            'description' => $assignment->due->description,
            'amount' => $this->formatAmount((int) ($payment?->amount_kobo ?? $assignment->amount_kobo)),
            'paidAt' => $assignment->paid_at?->toFormattedDateString() ?? 'Paid',
            ...$this->receiptPayload($payment),
        ];
    }

    private function receiptPayload(?DuePayment $payment): array
    {
        if (! $payment instanceof DuePayment || $payment->paystack_transaction_id === null) {
            return ['receiptStatus' => null, 'receiptUrl' => null, 'receiptRetryUrl' => null, 'receiptEmailStatus' => null, 'receiptEmailRetryUrl' => null];
        }

        $emailStatus = $payment->receiptEmailDelivery?->status;

        return [
            'receiptStatus' => $payment->receipt_status,
            'receiptUrl' => $payment->receipt_status === DuePayment::ReceiptStatusReady
                ? route('dashboard.dues.payments.receipt', ['paystackTransactionId' => $payment->paystack_transaction_id])
                : null,
            'receiptRetryUrl' => $payment->receipt_status === DuePayment::ReceiptStatusFailed
                ? route('dashboard.dues.payments.receipt.retry', ['paystackTransactionId' => $payment->paystack_transaction_id])
                : null,
            'receiptEmailStatus' => $emailStatus,
            'receiptEmailRetryUrl' => in_array($emailStatus, ['failed', 'skipped'], true)
                ? route('dashboard.dues.payments.receipt.email', ['paystackTransactionId' => $payment->paystack_transaction_id])
                : null,
        ];
    }

    private function paymentSettingsPayload(AdvisorProfile $advisor): array
    {
        $account = PaymentAccount::currentFor($advisor) ?? PaymentAccount::latestFor($advisor);

        return [
            'accounts' => $account instanceof PaymentAccount ? [$this->paymentAccountPayload($account)] : [],
            'dashboardUrl' => $this->paystackDashboardUrl(),
            'createUrl' => route('dashboard.payment-settings.create'),
        ];
    }

    private function paymentAccountFormPayload(AdvisorProfile $advisor): array
    {
        $account = PaymentAccount::currentFor($advisor) ?? PaymentAccount::latestFor($advisor);
        $banks = $this->paystackBankOptionsPayload();

        return [
            'accounts' => $account instanceof PaymentAccount ? [$this->paymentAccountPayload($account)] : [],
            'bankOptions' => $banks['options'],
            'bankOptionsError' => $banks['error'],
            'dashboardUrl' => $this->paystackDashboardUrl(),
            'returnUrl' => route('dashboard.section', ['section' => 'payment-settings']),
        ];
    }

    private function paymentAccountPayload(PaymentAccount $account): array
    {
        return [
            'id' => (int) $account->id,
            'label' => $account->label,
            'businessName' => $account->business_name,
            'bankName' => $account->bank_name,
            'accountName' => $account->account_name,
            'accountNumber' => $account->account_number,
            'status' => match ($account->status) {
                PaymentAccount::StatusActive => 'Active',
                PaymentAccount::StatusDisabled => 'Disabled',
                default => 'Draft',
            },
            'set' => 'All student dues',
            'entryAcademicSessionId' => null,
            'verifiedAt' => $account->verified_at?->toFormattedDateString(),
        ];
    }

    private function advisorSettingsPayload(AdvisorProfile $advisor, Collection $sets): array
    {
        return [
            'staff' => [
                'name' => $advisor->user->name,
                'email' => $advisor->user->email,
                'avatar' => $advisor->user->profilePhotoUrl(),
            ],
            'institution' => $advisor->institution_name,
            'programme' => $this->programmePayload($advisor),
            'assignedSets' => $sets->map(fn (StudentSet $set): array => $this->studentSetPayload($set))->all(),
        ];
    }

    private function studentProfilePayload(StudentProfile $student): array
    {
        $summary = $this->academicPerformance->summary($student);
        [$surname, $otherNames] = $this->splitName($student->user->name);

        return [
            'name' => ['full' => $student->user->name, 'surname' => $surname, 'otherNames' => $otherNames],
            'email' => (string) $student->user->email,
            'avatar' => $student->user->profilePhotoUrl(),
            'registrationNumber' => $student->registration_number,
            'cgpa' => $summary['cgpa'],
            'institution' => $student->advisorProfile->institution_name,
            'programme' => $this->programmePayload($student->advisorProfile),
            'currentLevel' => $student->currentLevel instanceof AcademicYear ? $this->levelPayload($student->currentLevel) : null,
            'courseAdvisor' => $student->advisorProfile->user->name,
        ];
    }

    private function studentRecordPayload(StudentProfile $student): array
    {
        $summary = $this->academicPerformance->summary($student);
        $levelGpas = $this->academicPerformance->gpaByAcademicYear($student, (int) $student->studentSet->minimum_years_to_degree);
        $semesterResults = $this->academicPerformance->publishedSemesterResults($student);

        return [
            'student' => $this->staffStudentPayload($student, [
                'cgpa' => $summary['cgpa'],
                'levelGpas' => $levelGpas->map(fn (array $item): array => ['level' => $item['level'], 'gpa' => $item['gpa']])->all(),
                'pendingCarryovers' => $this->academicPerformance->carryovers($student)->pluck('code')->all(),
            ]),
            'statementUrl' => route('dashboard.students.statement', ['registrationNumber' => $student->registration_number]),
            'institution' => $student->advisorProfile->institution_name,
            'programme' => $this->programmePayload($student->advisorProfile),
            'entrySession' => $this->academicSessionPayload($student->studentSet->entryAcademicSession),
            'courseAdvisor' => $student->advisorProfile->user->name,
            'cgpa' => $summary['cgpa'],
            'levelGpas' => $levelGpas->map(fn (array $item): array => ['level' => $item['level']->name, 'gpa' => $item['gpa']])->all(),
            'semesters' => $semesterResults->map(fn (StudentSemesterResult $result): array => [
                'id' => (string) $result->id,
                'level' => $this->levelPayload($result->resultSheet->academicYearSemester->academicYear),
                'semester' => $this->semesterPayload($result->resultSheet->academicYearSemester->semester),
                'gpa' => number_format((float) $result->gpa, 2),
                'totalUnits' => (int) $result->total_units,
                'courses' => $result->courseResults->map(fn ($grade): array => [
                    'code' => $grade->resultSheetCourse->course_code,
                    'title' => $grade->resultSheetCourse->course_title,
                    'units' => (int) $grade->resultSheetCourse->credit_units,
                    'grade' => $grade->grade,
                ])->all(),
            ])->all(),
        ];
    }

    private function studentHeaderPayload(StudentProfile $student): array
    {
        return [
            'registrationNumber' => $student->registration_number,
            'currentLevel' => $student->currentLevel instanceof AcademicYear ? $this->levelPayload($student->currentLevel) : null,
        ];
    }

    private function studentSetPayload(StudentSet $set): array
    {
        $set->loadMissing(['entryAcademicSession', 'advisorProfile.user']);
        $currentLevel = $this->academicContext->academicYearForSet($set);

        return [
            'id' => (int) $set->id,
            'name' => $set->name,
            'entrySession' => $this->academicSessionPayload($set->entryAcademicSession),
            'currentLevel' => $currentLevel instanceof AcademicYear ? $this->levelPayload($currentLevel) : null,
            'advisorName' => $set->advisorProfile->user->name,
            'minimumYearsToDegree' => (int) $set->minimum_years_to_degree,
            'isGraduated' => $this->academicContext->setHasGraduated($set),
        ];
    }

    private function academicSessionPayload(AcademicSession $session): array
    {
        return [
            'id' => (int) $session->id,
            'name' => $session->name,
            'startsOn' => $session->starts_on->toDateString(),
            'endsOn' => $session->ends_on->toDateString(),
            'status' => today()->betweenIncluded($session->starts_on, $session->ends_on)
                ? 'Current'
                : ($session->starts_on->isFuture() ? 'Upcoming' : 'Past'),
        ];
    }

    private function levelPayload(AcademicYear $level): array
    {
        return [
            'id' => (int) $level->id,
            'title' => $level->name,
            'slug' => $level->slug,
            'yearNumber' => (int) $level->year_number,
        ];
    }

    private function semesterPayload(Semester $semester): array
    {
        return ['id' => (int) $semester->id, 'name' => $semester->name, 'slug' => $semester->slug];
    }

    private function programmePayload(AdvisorProfile $advisor): array
    {
        return [
            'id' => (int) $advisor->id,
            'name' => $advisor->programme_name,
            'abbreviation' => $advisor->programme_abbreviation,
            'minimumYearsToDegree' => (int) $advisor->minimum_years_to_degree,
        ];
    }

    private function provisionStudent(AdvisorProfile $advisor, StudentSet $set, string $registrationNumber, ?string $accessCode): StudentProfile
    {
        $existing = $advisor->students()->where('registration_number', $registrationNumber)->first();

        if ($existing instanceof StudentProfile) {
            return $existing->load('user');
        }

        $user = User::query()->create([
            'name' => 'Student '.$registrationNumber,
            'email' => null,
            'password' => Str::password(32),
            'access_code' => $accessCode ?: $this->generateAccessCode(),
            'status' => AccountStatus::Pending,
            'account_type' => AccountType::Student,
        ]);

        return StudentProfile::query()->create([
            'user_id' => $user->id,
            'advisor_profile_id' => $advisor->id,
            'student_set_id' => $set->id,
            'current_academic_year_id' => $this->academicContext->academicYearForSet($set)?->id,
            'registration_number' => $registrationNumber,
            'status' => StudentStatus::Active,
        ])->load('user');
    }

    private function generateAccessCode(): string
    {
        do {
            $code = Str::upper(Str::random(8));
        } while (User::query()->where('access_code', $code)->whereNull('access_code_used_at')->exists());

        return $code;
    }

    private function authorizedUser(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && in_array($user->account_type, [AccountType::Adviser, AccountType::Student], true), 403);

        return $user;
    }

    private function authorizedAdvisor(Request $request): AdvisorProfile
    {
        $user = $this->authorizedUser($request);
        abort_unless($user->account_type === AccountType::Adviser && $user->advisorProfile instanceof AdvisorProfile, 403);

        return $user->advisorProfile->loadMissing('user');
    }

    private function authorizedStudent(Request $request): StudentProfile
    {
        $user = $this->authorizedUser($request);
        abort_unless($user->account_type === AccountType::Student && $user->studentProfile instanceof StudentProfile, 403);

        return $user->studentProfile->loadMissing(['user', 'advisorProfile.user', 'studentSet.entryAcademicSession', 'currentLevel']);
    }

    private function advisorForUser(User $user): AdvisorProfile
    {
        $advisor = $user->account_type === AccountType::Adviser ? $user->advisorProfile : $user->studentProfile?->advisorProfile;
        abort_unless($advisor instanceof AdvisorProfile, 404);

        return $advisor->loadMissing('user');
    }

    private function dashboardRole(User $user): string
    {
        return $user->account_type === AccountType::Adviser ? 'staff' : 'student';
    }

    private function advisorStudentSet(AdvisorProfile $advisor, int $studentSetId): StudentSet
    {
        return $advisor->studentSets()->with('entryAcademicSession')->findOrFail($studentSetId);
    }

    private function selectedAdvisorSet(Request $request, AdvisorProfile $advisor): StudentSet
    {
        $setId = $request->integer('set') ?: $request->integer('student_set') ?: $request->integer('entry_session');

        return $setId > 0
            ? $this->advisorStudentSet($advisor, $setId)
            : $advisor->studentSets()->with('entryAcademicSession')->latest('entry_academic_session_id')->firstOrFail();
    }

    private function levelsFor(AdvisorProfile $advisor): Collection
    {
        return AcademicYear::query()
            ->where('year_number', '<=', $advisor->minimum_years_to_degree)
            ->orderBy('year_number')
            ->get();
    }

    private function resolveLevel(string $slug, StudentSet $set): AcademicYear
    {
        return AcademicYear::query()->where('slug', $slug)->where('year_number', '<=', $set->minimum_years_to_degree)->firstOrFail();
    }

    private function resolveSemester(string $slug): Semester
    {
        return Semester::query()->where('slug', $slug)->firstOrFail();
    }

    private function yearSemester(AcademicYear $year, Semester $semester): AcademicYearSemester
    {
        return AcademicYearSemester::query()->whereBelongsTo($year)->whereBelongsTo($semester)->firstOrFail();
    }

    private function requiredSessionForSetYear(StudentSet $set, AcademicYear $year): AcademicSession
    {
        $session = $this->academicContext->sessionForSetYear($set, $year);

        if (! $session instanceof AcademicSession) {
            throw ValidationException::withMessages([
                'session' => 'Create the academic session for this student set and level before continuing.',
            ]);
        }

        return $session;
    }

    private function requiredCurrentSession(AdvisorProfile $advisor): AcademicSession
    {
        $session = $this->academicContext->currentSession($advisor) ?? $advisor->academicSessions()->latest('starts_on')->first();

        if (! $session instanceof AcademicSession) {
            throw ValidationException::withMessages(['session' => 'Add an academic session before continuing.']);
        }

        return $session;
    }

    /** @return array{0: AcademicSession, 1: AcademicYearSemester} */
    private function currentRegistrationContext(StudentProfile $student): array
    {
        $context = $this->currentRegistrationContextOrNull($student);

        if ($context === null) {
            throw ValidationException::withMessages([
                'registration' => 'Course registration is not available until the current academic session and semester are configured.',
            ]);
        }

        return $context;
    }

    /** @return array{0: AcademicSession, 1: AcademicYearSemester}|null */
    private function currentRegistrationContextOrNull(StudentProfile $student): ?array
    {
        $session = $this->academicContext->currentSession($student->advisorProfile)
            ?? $student->advisorProfile->academicSessions()->latest('starts_on')->first();
        $year = $session instanceof AcademicSession ? $this->academicContext->academicYearForStudent($student, $session) : null;
        $semester = $session instanceof AcademicSession ? $this->academicContext->currentSemesterInstance($session)?->semester : null;
        $semester ??= Semester::query()->orderBy('sort_order')->first();

        if (! $session instanceof AcademicSession || ! $year instanceof AcademicYear || ! $semester instanceof Semester) {
            return null;
        }

        return [$session, $this->yearSemester($year, $semester)->loadMissing(['academicYear', 'semester'])];
    }

    /** @param array<int, int|string> $ids @return array<int, int> */
    private function validPrerequisiteIds(
        StudentSet $set,
        AcademicSession $session,
        AcademicYearSemester $target,
        array $ids,
        ?int $excludedCourseId = null,
    ): array {
        $ids = collect($ids)->map(fn ($id): int => (int) $id)->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return [];
        }

        $valid = CourseOffering::query()
            ->where('student_set_id', $set->id)
            ->whereIn('course_id', $ids)
            ->whereNull('archived_at')
            ->where(function ($query) use ($session, $target): void {
                $query->where('academic_session_id', '<', $session->id)
                    ->orWhere(fn ($query) => $query->where('academic_session_id', $session->id)->where('academic_year_semester_id', '<', $target->id));
            })
            ->pluck('course_id')
            ->unique();

        if ($excludedCourseId !== null) {
            $valid = $valid->reject(fn ($id): bool => (int) $id === $excludedCourseId);
        }

        if ($ids->diff($valid)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'prerequisite_course_ids' => 'Every prerequisite must be a course this set was offered in an earlier semester.',
            ]);
        }

        return $valid->map(fn ($id): int => (int) $id)->all();
    }

    private function ensureOfferingContext(
        CourseOffering $offering,
        StudentSet $set,
        AcademicSession $session,
        AcademicYear $year,
        Semester $semester,
    ): void {
        $slot = $this->yearSemester($year, $semester);
        abort_unless(
            (int) $offering->student_set_id === (int) $set->id
            && (int) $offering->academic_session_id === (int) $session->id
            && (int) $offering->academic_year_semester_id === (int) $slot->id,
            404,
        );
    }

    private function ensureCanViewResultSheet(User $user, ResultSheet $sheet): void
    {
        if ($user->account_type === AccountType::Adviser) {
            abort_unless((int) $sheet->advisor_profile_id === (int) $user->advisorProfile?->id, 404);

            return;
        }

        abort_unless(
            $sheet->status === ResultSheet::StatusPublished
            && $sheet->studentResults()->where('student_profile_id', $user->studentProfile?->id)->exists(),
            404,
        );
    }

    private function ensureAdvisorOwnsRegistration(AdvisorProfile $advisor, CourseRegistration $registration): void
    {
        abort_unless((int) $registration->advisor_profile_id === (int) $advisor->id, 404);
    }

    private function ensureAdvisorOwnsDue(AdvisorProfile $advisor, Due $due): void
    {
        abort_unless((int) $due->advisor_profile_id === (int) $advisor->id, 404);
    }

    private function ensureAdvisorOwnsAssignment(AdvisorProfile $advisor, Due $due, StudentDueAssignment $assignment): void
    {
        $this->ensureAdvisorOwnsDue($advisor, $due);
        abort_unless((int) $assignment->due_id === (int) $due->id, 404);
    }

    private function userCanViewPayment(User $user, DuePayment $payment): bool
    {
        return $user->account_type === AccountType::Adviser
            ? (int) $payment->advisor_profile_id === (int) $user->advisorProfile?->id
            : (int) $payment->student_profile_id === (int) $user->studentProfile?->id;
    }

    /** @return array<int, string> */
    private function resultSheetRelations(): array
    {
        return [
            'academicSession',
            'studentSet.entryAcademicSession',
            'academicYearSemester.academicYear',
            'academicYearSemester.semester',
            'createdBy',
            'courses.courseOffering',
            'studentResults.studentProfile.user',
            'studentResults.courseResults',
            'revisions',
        ];
    }

    private function qualifiedSemester(AcademicYearSemester $slot): string
    {
        return Str::replace(' Level', 'L', $slot->academicYear->name).' '.$slot->semester->name.' Semester';
    }

    private function registrationItemPayload(CourseRegistrationItem $item): array
    {
        return [
            'courseOfferingId' => (int) $item->course_offering_id,
            'code' => $item->course_code,
            'title' => $item->course_title,
            'units' => (int) $item->credit_units,
            'category' => $this->courseCategoryLabel($item->category),
            'source' => $item->source,
            'sourceLabel' => Str::headline($item->source),
            'isCarryover' => $item->source === CourseRegistrationItem::SourceCarryover,
            'selectable' => false,
            'blockReasons' => [],
            'originSemester' => null,
        ];
    }

    private function registrationStatusLabel(string $status): string
    {
        return match ($status) {
            CourseRegistration::StatusSubmitted => 'In review',
            CourseRegistration::StatusApproved => 'Approved',
            CourseRegistration::StatusReturned => 'Changes requested',
            default => 'Draft',
        };
    }

    private function courseCategoryLabel(string $category): string
    {
        return CourseCategory::tryFrom($category)?->label() ?? Str::headline($category);
    }

    private function dueStatusLabel(string $status): string
    {
        return match ($status) {
            Due::StatusPublished => 'Published',
            Due::StatusArchived => 'Archived',
            default => 'Draft',
        };
    }

    private function formatAmount(int $amountKobo): string
    {
        return 'NGN '.number_format($amountKobo / 100, 2);
    }

    /** @return array{0: string, 1: string} */
    private function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name), 2);

        return [$parts[0] ?? '', $parts[1] ?? ''];
    }

    /** @return array<string, mixed> */
    private function updateOrReplaceSubaccount(
        PaystackService $paystack,
        PaymentAccount $account,
        array $validated,
        string $description,
    ): array {
        try {
            return $paystack->updateSubaccount(
                (string) $account->paystack_subaccount_code,
                $validated['business_name'],
                $validated['bank_code'],
                $validated['account_number'],
                $description,
            );
        } catch (PaystackException $exception) {
            if (! $exception->isInvalidSubaccount()) {
                throw $exception;
            }

            return $paystack->createSubaccount(
                $validated['business_name'],
                $validated['bank_code'],
                $validated['account_number'],
                $description,
            );
        }
    }

    /** @return array{code: string, name: string} */
    private function paystackBankFromCode(PaystackService $paystack, string $bankCode): array
    {
        try {
            $bank = collect($paystack->nigerianBankOptions())->firstWhere('code', $bankCode);
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'bank_code' => 'Paystack could not load the supported banks right now. Try again shortly.',
            ]);
        }

        if (! is_array($bank)) {
            throw ValidationException::withMessages(['bank_code' => 'Select a bank from the Paystack bank list.']);
        }

        return ['code' => (string) $bank['code'], 'name' => (string) $bank['name']];
    }

    /** @return array{options: array<int, array{code: string, name: string}>, error: string|null} */
    private function paystackBankOptionsPayload(): array
    {
        try {
            $options = app(PaystackService::class)->nigerianBankOptions();
        } catch (\Throwable) {
            return ['options' => [], 'error' => 'Paystack banks could not be loaded. Check the Paystack keys or try again shortly.'];
        }

        return $options === []
            ? ['options' => [], 'error' => 'Paystack did not return any Nigerian banks. Try again shortly.']
            : ['options' => $options, 'error' => null];
    }

    private function paystackDashboardUrl(): string
    {
        $url = config('services.paystack.dashboard_url');

        return is_string($url) && $url !== '' ? $url : 'https://dashboard.paystack.com';
    }
}
