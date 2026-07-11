<?php

use App\AccountStatus;
use App\AccountType;
use App\CourseCategory;
use App\Jobs\GenerateDuePaymentReceipt;
use App\Models\AcademicSession;
use App\Models\AcademicYear;
use App\Models\AcademicYearSemester;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\CourseRegistrationItem;
use App\Models\Department;
use App\Models\Due;
use App\Models\DuePayment;
use App\Models\EmailDelivery;
use App\Models\Faculty;
use App\Models\ImportBatch;
use App\Models\PaymentAccount;
use App\Models\ResultSheet;
use App\Models\ResultSheetCourse;
use App\Models\School;
use App\Models\Semester;
use App\Models\StaffProfile;
use App\Models\StudentCourseResult;
use App\Models\StudentDueAssignment;
use App\Models\StudentProfile;
use App\Models\StudentSemesterResult;
use App\Models\StudentSetAdvisorAssignment;
use App\Models\User;
use App\Services\GpaCalculator;
use App\Services\PaystackService;
use App\Services\ResultSheetImporter;
use Carbon\CarbonImmutable;
use Database\Seeders\AcademicStructureSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('student users see the student dashboard shell', function () {
    $this->seed(AcademicStructureSeeder::class);

    $department = Department::factory()->create();
    $department->load('faculty.school');
    $school = $department->faculty->school;
    $currentLevel = AcademicYear::query()->where('slug', '400-level')->firstOrFail();
    $entrySession = AcademicSession::factory()->create([
        'school_id' => $school->id,
        'name' => '2021/2022',
        'starts_on' => '2021-09-01',
        'ends_on' => '2022-08-31',
    ]);
    AcademicSession::factory()->create([
        'school_id' => $school->id,
        'name' => '2022/2023',
        'starts_on' => '2022-09-01',
        'ends_on' => '2023-08-31',
    ]);
    AcademicSession::factory()->create([
        'school_id' => $school->id,
        'name' => '2023/2024',
        'starts_on' => '2023-09-01',
        'ends_on' => '2024-08-31',
    ]);
    $currentSession = AcademicSession::factory()->create([
        'school_id' => $school->id,
        'name' => '2025/2026',
        'starts_on' => '2025-09-01',
        'ends_on' => '2026-08-31',
    ]);
    $user = User::factory()->student()->create([
        'school_id' => $school->id,
    ]);

    $studentProfile = StudentProfile::factory()->create([
        'school_id' => $school->id,
        'user_id' => $user->id,
        'department_id' => $department->id,
        'current_academic_year_id' => $currentLevel->id,
        'entry_academic_session_id' => $entrySession->id,
        'registration_number' => '2021009999',
    ]);
    $associationDue = createTestDue($department, $entrySession, $currentSession, 'Student Association Levy 400 Level', 500000);
    assignTestDue($associationDue, $studentProfile);
    $departmentDue = createTestDue($department, $entrySession, $currentSession, 'Departmental Dues 400 Level', 1500000);
    assignTestDue($departmentDue, $studentProfile);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->where('dashboard.role', 'student')
        ->where('dashboard.section', 'home')
        ->where('dashboard.roleLabel', 'Student')
        ->where('dashboard.student.registrationNumber', '2021009999')
        ->where('dashboard.student.currentLevel.title', '400 Level')
        ->has('dashboard.levels', 4)
        ->where('dashboard.levels.0.slug', '100-level')
        ->where('dashboard.levels.3.slug', '400-level')
        ->where('dashboard.studentHome.cgpa', null)
        ->where('dashboard.studentHome.currentSemester.name', 'Rain')
        ->where('dashboard.studentHome.currentSemester.session', '2025/2026')
        ->where('dashboard.studentHome.totalCourseUnits', 0)
        ->has('dashboard.studentHome.courses', 0)
        ->has('dashboard.studentHome.pendingDues', 2)
        ->where('dashboard.studentHome.pendingDues.0.title', 'Departmental Dues 400 Level')
        ->where('dashboard.studentHome.pendingDues.0.amount', 'NGN 15,000')
        ->has('dashboard.studentHome.resits', 0)
        ->where('dashboard.staffHome', null)
        ->where('dashboard.studentResults', null)
        ->where('dashboard.studentDues', null)
        ->etc());
});

test('student course registration starts empty and derives carryovers from approved results', function () {
    $this->travelTo(CarbonImmutable::parse('2026-01-15'));
    $this->seed(AcademicStructureSeeder::class);

    $department = Department::factory()->create();
    $department->load('faculty.school');
    $school = $department->faculty->school;
    $currentLevel = AcademicYear::query()->where('slug', '300-level')->firstOrFail();
    $harmattan = Semester::query()->where('slug', 'harmattan')->firstOrFail();
    $currentYearSemester = AcademicYearSemester::query()
        ->where('academic_year_id', $currentLevel->id)
        ->where('semester_id', $harmattan->id)
        ->firstOrFail();
    $carryoverLevel = AcademicYear::query()->where('slug', '200-level')->firstOrFail();
    $carryoverYearSemester = AcademicYearSemester::query()
        ->where('academic_year_id', $carryoverLevel->id)
        ->where('semester_id', $harmattan->id)
        ->firstOrFail();
    $entrySession = AcademicSession::factory()->create([
        'school_id' => $school->id,
        'name' => '2023/2024',
        'starts_on' => '2023-09-01',
        'ends_on' => '2024-08-31',
    ]);
    $secondSession = AcademicSession::factory()->create([
        'school_id' => $school->id,
        'name' => '2024/2025',
        'starts_on' => '2024-09-01',
        'ends_on' => '2025-08-31',
    ]);
    $currentSession = AcademicSession::factory()->create([
        'school_id' => $school->id,
        'name' => '2025/2026',
        'starts_on' => '2025-09-01',
        'ends_on' => '2026-08-31',
    ]);
    $user = User::factory()->student()->create([
        'school_id' => $school->id,
        'name' => 'Ada Nwosu',
    ]);

    $studentProfile = StudentProfile::factory()->create([
        'school_id' => $school->id,
        'user_id' => $user->id,
        'department_id' => $department->id,
        'current_academic_year_id' => $currentLevel->id,
        'entry_academic_session_id' => $entrySession->id,
        'registration_number' => '2023001001',
    ]);

    $regularCourse = Course::factory()->create([
        'school_id' => $school->id,
        'code' => 'CPE 301',
        'title' => 'Computer Architecture',
        'slug' => 'cpe-301-computer-architecture',
        'credit_units' => 3,
    ]);
    CourseOffering::factory()->create([
        'owner_type' => Department::class,
        'owner_id' => $department->id,
        'course_id' => $regularCourse->id,
        'academic_session_id' => $currentSession->id,
        'academic_year_semester_id' => $currentYearSemester->id,
        'category' => CourseCategory::Departmental,
    ]);

    $carryoverCourse = Course::factory()->create([
        'school_id' => $school->id,
        'code' => 'MTH 201',
        'title' => 'Engineering Mathematics III',
        'slug' => 'mth-201-engineering-mathematics-iii',
        'credit_units' => 3,
    ]);
    $historicalCarryoverOffering = CourseOffering::factory()->create([
        'owner_type' => Department::class,
        'owner_id' => $department->id,
        'course_id' => $carryoverCourse->id,
        'academic_session_id' => $secondSession->id,
        'academic_year_semester_id' => $carryoverYearSemester->id,
        'category' => CourseCategory::Departmental,
    ]);
    CourseOffering::factory()->create([
        'owner_type' => Department::class,
        'owner_id' => $department->id,
        'course_id' => $carryoverCourse->id,
        'academic_session_id' => $currentSession->id,
        'academic_year_semester_id' => $carryoverYearSemester->id,
        'category' => CourseCategory::Departmental,
    ]);
    createApprovedCourseResult(
        $department,
        $studentProfile,
        $secondSession,
        $carryoverYearSemester,
        $historicalCarryoverOffering,
        'F',
    );

    $response = $this->actingAs($user)->get(route('dashboard.section', [
        'section' => 'course-registration',
    ]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->where('dashboard.role', 'student')
        ->where('dashboard.section', 'course-registration')
        ->where('dashboard.sectionTitle', 'Course Registration')
        ->has('dashboard.studentCourseRegistration.selectedCourses', 0)
        ->where('dashboard.studentCourseRegistration.availableCourses', fn (mixed $courses): bool => collect($courses)->contains(
            fn (array $course): bool => $course['code'] === 'CPE 301'
                && $course['sourceLabel'] === 'Current Set'
                && $course['isCarryover'] === false
        ) && collect($courses)->contains(
            fn (array $course): bool => $course['code'] === 'MTH 201'
                && $course['sourceLabel'] === 'Carryover'
                && $course['isCarryover'] === true
        ))
        ->etc());
});

test('course registration shows outstanding requirements and blocks failed prerequisites on submit', function () {
    $this->travelTo(CarbonImmutable::parse('2026-01-15'));
    $this->seed(AcademicStructureSeeder::class);

    $department = Department::factory()->create();
    $department->load('faculty.school');
    $school = $department->faculty->school;
    $currentLevel = AcademicYear::query()->where('slug', '300-level')->firstOrFail();
    $previousLevel = AcademicYear::query()->where('slug', '200-level')->firstOrFail();
    $harmattan = Semester::query()->where('slug', 'harmattan')->firstOrFail();
    $currentYearSemester = AcademicYearSemester::query()
        ->where('academic_year_id', $currentLevel->id)
        ->where('semester_id', $harmattan->id)
        ->firstOrFail();
    $previousYearSemester = AcademicYearSemester::query()
        ->where('academic_year_id', $previousLevel->id)
        ->where('semester_id', $harmattan->id)
        ->firstOrFail();
    $entrySession = AcademicSession::factory()->create([
        'school_id' => $school->id,
        'name' => '2023/2024',
        'starts_on' => '2023-09-01',
        'ends_on' => '2024-08-31',
    ]);
    $previousSession = AcademicSession::factory()->create([
        'school_id' => $school->id,
        'name' => '2024/2025',
        'starts_on' => '2024-09-01',
        'ends_on' => '2025-08-31',
    ]);
    $currentSession = AcademicSession::factory()->create([
        'school_id' => $school->id,
        'name' => '2025/2026',
        'starts_on' => '2025-09-01',
        'ends_on' => '2026-08-31',
    ]);
    $user = User::factory()->student()->create([
        'school_id' => $school->id,
        'name' => 'Chinaza Okoro',
    ]);
    $studentProfile = StudentProfile::factory()->create([
        'school_id' => $school->id,
        'user_id' => $user->id,
        'department_id' => $department->id,
        'current_academic_year_id' => $currentLevel->id,
        'entry_academic_session_id' => $entrySession->id,
        'registration_number' => '2023001004',
    ]);
    $prerequisiteCourse = Course::factory()->create([
        'school_id' => $school->id,
        'code' => 'MTH 201',
        'title' => 'Engineering Mathematics III',
        'slug' => 'mth-201-engineering-mathematics-iii',
        'credit_units' => 3,
    ]);
    $historicalPrerequisiteOffering = CourseOffering::factory()->create([
        'owner_type' => Department::class,
        'owner_id' => $department->id,
        'course_id' => $prerequisiteCourse->id,
        'academic_session_id' => $previousSession->id,
        'academic_year_semester_id' => $previousYearSemester->id,
        'category' => CourseCategory::Departmental,
    ]);
    CourseOffering::factory()->create([
        'owner_type' => Department::class,
        'owner_id' => $department->id,
        'course_id' => $prerequisiteCourse->id,
        'academic_session_id' => $currentSession->id,
        'academic_year_semester_id' => $previousYearSemester->id,
        'category' => CourseCategory::Departmental,
    ]);
    createApprovedCourseResult(
        $department,
        $studentProfile,
        $previousSession,
        $previousYearSemester,
        $historicalPrerequisiteOffering,
        'F',
    );
    $outstandingCourse = Course::factory()->create([
        'school_id' => $school->id,
        'code' => 'EEE 203',
        'title' => 'Circuit Theory',
        'slug' => 'eee-203-circuit-theory',
        'credit_units' => 2,
    ]);
    CourseOffering::factory()->create([
        'owner_type' => Department::class,
        'owner_id' => $department->id,
        'course_id' => $outstandingCourse->id,
        'academic_session_id' => $previousSession->id,
        'academic_year_semester_id' => $previousYearSemester->id,
        'category' => CourseCategory::Departmental,
    ]);
    CourseOffering::factory()->create([
        'owner_type' => Department::class,
        'owner_id' => $department->id,
        'course_id' => $outstandingCourse->id,
        'academic_session_id' => $currentSession->id,
        'academic_year_semester_id' => $previousYearSemester->id,
        'category' => CourseCategory::Departmental,
    ]);
    $currentCourse = Course::factory()->create([
        'school_id' => $school->id,
        'code' => 'CPE 301',
        'title' => 'Computer Architecture',
        'slug' => 'cpe-301-computer-architecture',
        'credit_units' => 3,
    ]);
    $currentOffering = CourseOffering::factory()->create([
        'owner_type' => Department::class,
        'owner_id' => $department->id,
        'course_id' => $currentCourse->id,
        'academic_session_id' => $currentSession->id,
        'academic_year_semester_id' => $currentYearSemester->id,
        'category' => CourseCategory::Departmental,
    ]);
    $currentOffering->prerequisiteCourses()->sync([$prerequisiteCourse->id]);

    $this->actingAs($user)->get(route('dashboard.section', [
        'section' => 'course-registration',
    ]))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.studentCourseRegistration.availableCourses', fn (mixed $courses): bool => collect($courses)->contains(
                fn (array $course): bool => $course['code'] === 'EEE 203'
                    && $course['sourceLabel'] === 'Outstanding Requirement'
                    && $course['selectable'] === true
            ) && collect($courses)->contains(
                fn (array $course): bool => $course['code'] === 'CPE 301'
                    && $course['sourceLabel'] === 'Current Set'
                    && in_array('MTH 201 was last recorded as F.', $course['blockReasons'], true)
            ))
            ->etc());

    $this->actingAs($user)->post(route('dashboard.course-registration.store'), [
        'action' => 'submit',
        'items' => [
            [
                'course_offering_id' => $currentOffering->id,
                'source' => CourseRegistrationItem::SourceCurrentSet,
            ],
        ],
    ])->assertSessionHasErrors('items');

    $this->actingAs($user)->post(route('dashboard.course-registration.store'), [
        'action' => 'save',
        'items' => [
            [
                'course_offering_id' => $currentOffering->id,
                'source' => CourseRegistrationItem::SourceCurrentSet,
            ],
        ],
    ])->assertRedirect(route('dashboard.section', ['section' => 'course-registration']));
});

test('student users see pending and paid dues records', function () {
    $this->seed(AcademicStructureSeeder::class);

    $department = Department::factory()->create();
    $department->load('faculty.school');
    $school = $department->faculty->school;
    $currentLevel = AcademicYear::query()->where('slug', '300-level')->firstOrFail();
    $entrySession = AcademicSession::factory()->create([
        'school_id' => $school->id,
        'name' => '2023/2024',
        'starts_on' => '2023-09-01',
        'ends_on' => '2024-08-31',
    ]);
    $currentSession = AcademicSession::factory()->create([
        'school_id' => $school->id,
        'name' => '2025/2026',
        'starts_on' => '2025-09-01',
        'ends_on' => '2026-08-31',
    ]);
    $user = User::factory()->student()->create([
        'school_id' => $school->id,
    ]);

    $studentProfile = StudentProfile::factory()->create([
        'school_id' => $school->id,
        'user_id' => $user->id,
        'department_id' => $department->id,
        'current_academic_year_id' => $currentLevel->id,
        'entry_academic_session_id' => $entrySession->id,
    ]);
    $staffProfile = StaffProfile::factory()->create([
        'department_id' => $department->id,
    ]);
    $paymentAccount = createTestPaymentAccount($department, $staffProfile);
    $associationDue = createTestDue($department, $entrySession, $currentSession, 'Student Association Levy 300 Level', 500000, $staffProfile, $paymentAccount);
    assignTestDue($associationDue, $studentProfile);
    $departmentDue = createTestDue($department, $entrySession, $currentSession, 'Departmental Dues 300 Level', 1500000, $staffProfile, $paymentAccount);
    assignTestDue($departmentDue, $studentProfile);
    $insuranceDue = createTestDue($department, $entrySession, $currentSession, 'Student Insurance', 300000, $staffProfile, $paymentAccount);
    markTestDuePaid(assignTestDue($insuranceDue, $studentProfile), '2026-05-03 10:00:00');
    $facultyDue = createTestDue($department, $entrySession, $currentSession, 'Faculty Development Levy', 800000, $staffProfile, $paymentAccount);
    markTestDuePaid(assignTestDue($facultyDue, $studentProfile), '2026-05-04 10:00:00');

    $response = $this->actingAs($user)->get(route('dashboard.section', [
        'section' => 'dues',
    ]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->where('dashboard.role', 'student')
        ->where('dashboard.section', 'dues')
        ->where('dashboard.studentHome', null)
        ->where('dashboard.studentResults', null)
        ->has('dashboard.studentDues', fn (Assert $page) => $page
            ->where('pendingAmount', 'NGN 20,000')
            ->where('paidAmount', 'NGN 11,000')
            ->where('totalDues', 4)
            ->has('pending', 2)
            ->where('pending.0.name', 'Departmental Dues 300 Level')
            ->where('pending.0.amount', 'NGN 15,000')
            ->where('pending.0.paymentUrl', route('dashboard.dues.payments.store', [
                'studentDueAssignment' => $departmentDue->assignments()->firstOrFail(),
            ]))
            ->has('paid', 2)
        )
        ->etc());
});

test('students start Paystack checkout for a pending due', function () {
    $this->seed(AcademicStructureSeeder::class);

    Config::set('services.paystack.secret_key', 'sk_test_grade_manager');
    Config::set('services.paystack.channels', ['card', 'bank_transfer']);
    Config::set('services.paystack.split_fee_bearer', 'subaccount');
    Http::preventStrayRequests();
    Http::fake([
        'https://api.paystack.co/transaction/initialize' => Http::response([
            'status' => true,
            'data' => [
                'authorization_url' => 'https://checkout.paystack.com/test-checkout',
                'access_code' => 'ACCS_test_checkout',
            ],
        ]),
    ]);

    $department = Department::factory()->create();
    $department->load('faculty.school');
    $school = $department->faculty->school;
    $currentLevel = AcademicYear::query()->where('slug', '300-level')->firstOrFail();
    $entrySession = AcademicSession::factory()->create([
        'school_id' => $school->id,
        'name' => '2023/2024',
        'starts_on' => '2023-09-01',
        'ends_on' => '2024-08-31',
    ]);
    $currentSession = AcademicSession::factory()->create([
        'school_id' => $school->id,
        'name' => '2025/2026',
        'starts_on' => '2025-09-01',
        'ends_on' => '2026-08-31',
    ]);
    $studentUser = User::factory()->student()->create([
        'school_id' => $school->id,
        'email' => 'student.payments@example.com',
    ]);
    $studentProfile = StudentProfile::factory()->create([
        'school_id' => $school->id,
        'user_id' => $studentUser->id,
        'department_id' => $department->id,
        'current_academic_year_id' => $currentLevel->id,
        'entry_academic_session_id' => $entrySession->id,
    ]);
    $staffProfile = StaffProfile::factory()->create([
        'department_id' => $department->id,
    ]);
    $previousStaffProfile = StaffProfile::factory()->create([
        'department_id' => $department->id,
    ]);
    $paymentAccount = createTestPaymentAccount($department, $staffProfile, $entrySession);
    $previousPaymentAccount = createTestPaymentAccount($department, $previousStaffProfile, $entrySession);
    $due = createTestDue($department, $entrySession, $currentSession, 'Departmental Dues 300 Level', 1500000, $staffProfile, $previousPaymentAccount);
    $assignment = assignTestDue($due, $studentProfile);

    $response = $this->actingAs($studentUser)
        ->withHeader('X-Inertia', 'true')
        ->post(route('dashboard.dues.payments.store', [
            'studentDueAssignment' => $assignment,
        ]));

    $response->assertStatus(409)
        ->assertHeader('X-Inertia-Location', 'https://checkout.paystack.com/test-checkout');
    Http::assertSent(fn ($request): bool => $request->url() === 'https://api.paystack.co/transaction/initialize'
        && $request->data()['email'] === 'student.payments@example.com'
        && $request->data()['amount'] === '1500000'
        && $request->data()['subaccount'] === $paymentAccount->paystack_subaccount_code
        && $request->data()['bearer'] === 'subaccount'
        && $request->data()['metadata']['student_due_assignment_id'] === $assignment->id);

    $payment = DuePayment::query()->firstOrFail();

    expect($payment->student_due_assignment_id)->toBe($assignment->id)
        ->and($payment->status)->toBe(DuePayment::StatusInitialized)
        ->and($payment->payment_account_id)->toBe($paymentAccount->id)
        ->and($payment->authorization_url)->toBe('https://checkout.paystack.com/test-checkout')
        ->and($payment->access_code)->toBe('ACCS_test_checkout')
        ->and(data_get($payment->provider_payload, 'payout_account.subaccount_code'))
        ->toBe($paymentAccount->paystack_subaccount_code);
});

test('an invalid Paystack subaccount returns students to dues without a server error', function () {
    $this->seed(AcademicStructureSeeder::class);

    Config::set('services.paystack.secret_key', 'sk_test_grade_manager');
    Http::preventStrayRequests();
    Http::fake([
        'https://api.paystack.co/transaction/initialize' => Http::response([
            'status' => false,
            'message' => 'Invalid Subaccount.',
        ], 404),
    ]);

    $department = Department::factory()->create();
    $department->load('faculty.school');
    $school = $department->faculty->school;
    $entrySession = AcademicSession::factory()->create(['school_id' => $school->id]);
    $currentSession = AcademicSession::factory()->create(['school_id' => $school->id]);
    $studentUser = User::factory()->student()->create([
        'school_id' => $school->id,
        'email' => 'invalid-subaccount@example.com',
    ]);
    $studentProfile = StudentProfile::factory()->create([
        'school_id' => $school->id,
        'user_id' => $studentUser->id,
        'department_id' => $department->id,
        'current_academic_year_id' => AcademicYear::query()->where('slug', '100-level')->firstOrFail()->id,
        'entry_academic_session_id' => $entrySession->id,
    ]);
    $staffProfile = StaffProfile::factory()->create([
        'department_id' => $department->id,
    ]);
    $paymentAccount = createTestPaymentAccount($department, $staffProfile);
    $due = createTestDue(
        $department,
        $entrySession,
        $currentSession,
        'Departmental Dues',
        1500000,
        $staffProfile,
        $paymentAccount,
    );
    $assignment = assignTestDue($due, $studentProfile);

    $this->actingAs($studentUser)
        ->post(route('dashboard.dues.payments.store', [
            'studentDueAssignment' => $assignment,
        ]))
        ->assertRedirect(route('dashboard.section', ['section' => 'dues']))
        ->assertSessionHas(
            'error',
            'Payment is temporarily unavailable for this due. Contact your course adviser.',
        );

    expect(DuePayment::query()->firstOrFail()->status)->toBe(DuePayment::StatusFailed)
        ->and($paymentAccount->refresh()->status)->toBe(PaymentAccount::StatusDisabled);
});

test('signed Paystack webhook marks a due as paid', function () {
    $this->seed(AcademicStructureSeeder::class);
    Queue::fake();

    Config::set('services.paystack.secret_key', 'sk_test_grade_manager');
    Http::preventStrayRequests();

    $department = Department::factory()->create();
    $department->load('faculty.school');
    $school = $department->faculty->school;
    $currentLevel = AcademicYear::query()->where('slug', '300-level')->firstOrFail();
    $entrySession = AcademicSession::factory()->create([
        'school_id' => $school->id,
        'name' => '2023/2024',
        'starts_on' => '2023-09-01',
        'ends_on' => '2024-08-31',
    ]);
    $currentSession = AcademicSession::factory()->create([
        'school_id' => $school->id,
        'name' => '2025/2026',
        'starts_on' => '2025-09-01',
        'ends_on' => '2026-08-31',
    ]);
    $studentUser = User::factory()->student()->create([
        'school_id' => $school->id,
    ]);
    $studentProfile = StudentProfile::factory()->create([
        'school_id' => $school->id,
        'user_id' => $studentUser->id,
        'department_id' => $department->id,
        'current_academic_year_id' => $currentLevel->id,
        'entry_academic_session_id' => $entrySession->id,
    ]);
    $staffProfile = StaffProfile::factory()->create([
        'department_id' => $department->id,
    ]);
    $paymentAccount = createTestPaymentAccount($department, $staffProfile, $entrySession);
    $due = createTestDue($department, $entrySession, $currentSession, 'Departmental Dues 300 Level', 1500000, $staffProfile, $paymentAccount);
    $assignment = assignTestDue($due, $studentProfile);
    $payment = DuePayment::query()->create([
        'student_due_assignment_id' => $assignment->id,
        'student_profile_id' => $studentProfile->id,
        'school_id' => $school->id,
        'payment_account_id' => $paymentAccount->id,
        'provider' => DuePayment::ProviderPaystack,
        'reference' => 'DUE-WEBHOOK-TEST',
        'status' => DuePayment::StatusInitialized,
        'amount_kobo' => 1500000,
        'currency' => 'NGN',
        'authorization_url' => 'https://checkout.paystack.com/test-checkout',
        'access_code' => 'ACCS_webhook_test',
    ]);

    Http::fake([
        'https://api.paystack.co/transaction/verify/*' => Http::response([
            'status' => true,
            'data' => [
                'id' => 987654,
                'status' => DuePayment::StatusSuccess,
                'reference' => $payment->reference,
                'amount' => 1500000,
                'currency' => 'NGN',
                'channel' => 'card',
                'fees' => 20000,
                'gateway_response' => 'Successful',
                'paid_at' => '2026-05-04T10:00:00+00:00',
                'metadata' => [
                    'school_id' => $school->id,
                    'due_id' => $due->id,
                    'student_due_assignment_id' => $assignment->id,
                    'student_profile_id' => $studentProfile->id,
                    'payment_account_id' => $paymentAccount->id,
                ],
            ],
        ]),
    ]);

    $rawPayload = json_encode([
        'event' => 'charge.success',
        'data' => [
            'reference' => $payment->reference,
        ],
    ], JSON_THROW_ON_ERROR);
    $signature = hash_hmac('sha512', $rawPayload, 'sk_test_grade_manager');

    $this->call('POST', route('paystack.webhook'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_PAYSTACK_SIGNATURE' => $signature,
    ], $rawPayload)->assertNoContent();

    expect($payment->refresh()->status)->toBe(DuePayment::StatusSuccess)
        ->and($payment->receipt_number)->not->toBeNull()
        ->and($payment->receipt_status)->toBe(DuePayment::ReceiptStatusPending)
        ->and($assignment->refresh()->status)->toBe(StudentDueAssignment::StatusPaid)
        ->and($assignment->latest_due_payment_id)->toBe($payment->id);

    Queue::assertPushed(GenerateDuePaymentReceipt::class);
});

test('student result levels stop at their current academic level', function () {
    $this->seed(AcademicStructureSeeder::class);

    $department = Department::factory()->create();
    $currentLevel = AcademicYear::query()->where('slug', '300-level')->firstOrFail();
    $department->load('faculty');
    $user = User::factory()->student()->create([
        'school_id' => $department->faculty->school_id,
    ]);

    StudentProfile::factory()->create([
        'school_id' => $department->faculty->school_id,
        'user_id' => $user->id,
        'department_id' => $department->id,
        'current_academic_year_id' => $currentLevel->id,
        'entry_academic_session_id' => null,
    ]);

    $response = $this->actingAs($user)->get(route('dashboard.level', [
        'section' => 'results',
        'level' => '300-level',
    ]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->where('dashboard.role', 'student')
        ->where('dashboard.section', 'results')
        ->where('dashboard.studentHome', null)
        ->where('dashboard.studentDues', null)
        ->has('dashboard.studentResults', fn (Assert $page) => $page
            ->where('level.title', '300 Level')
            ->where('averageGpa', null)
            ->where('totalUnits', 0)
            ->where('failedCourses', 0)
            ->where('totalCourses', 0)
            ->has('semesters', 2)
            ->where('semesters.0.name', 'Harmattan')
            ->where('semesters.0.gpa', null)
            ->where('semesters.0.totalUnits', 0)
            ->has('semesters.0.courses', 0)
            ->where('semesters.1.name', 'Rain')
            ->where('semesters.1.gpa', null)
            ->where('semesters.1.totalUnits', 0)
            ->has('semesters.1.courses', 0)
        )
        ->etc());

    $this->actingAs($user)->get(route('dashboard.level', [
        'section' => 'results',
        'level' => '400-level',
    ]))->assertNotFound();
});

test('faculty heads see the faculty head dashboard shell', function () {
    $this->seed(AcademicStructureSeeder::class);

    $faculty = Faculty::factory()->create();
    $department = Department::factory()->create([
        'faculty_id' => $faculty->id,
    ]);
    $user = User::factory()->staff()->create();
    $staffProfile = StaffProfile::factory()->create([
        'user_id' => $user->id,
        'department_id' => $department->id,
    ]);

    $faculty->assignHead($staffProfile);
    $studentUser = User::factory()->student()->create([
        'school_id' => $faculty->school_id,
        'name' => 'Ada Nwosu',
    ]);
    StudentProfile::factory()->create([
        'school_id' => $faculty->school_id,
        'user_id' => $studentUser->id,
        'department_id' => $department->id,
        'current_academic_year_id' => AcademicYear::query()->where('slug', '100-level')->firstOrFail()->id,
    ]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->where('dashboard.role', 'faculty_head')
        ->where('dashboard.section', 'home')
        ->where('dashboard.roleLabel', 'Faculty Head')
        ->where('dashboard.facultyHead.metrics.departments', 1)
        ->where('dashboard.facultyHead.metrics.hodCoverage', '0/1')
        ->where('dashboard.facultyHead.departments.0.status', 'Vacant')
        ->where('dashboard.facultyHead.students.0.fullName', 'Ada Nwosu')
        ->has('dashboard.facultyHead.students.0.levelGpas')
        ->where('dashboard.facultyHead.pendingTasks.0.status', 'Needs action')
        ->where('dashboard.staffHome', null)
        ->etc());

    $this->actingAs($user)->get(route('dashboard.section', [
        'section' => 'approvals',
    ]))->assertNotFound();
});

test('school admins see the school admin dashboard shell', function () {
    $school = School::factory()->create([
        'name' => 'Federal University of Technology Owerri',
        'abbreviation' => 'FUTO',
    ]);
    $faculty = Faculty::factory()->create([
        'school_id' => $school->id,
        'name' => 'Electrical Systems and Engineering Technology',
        'abbreviation' => 'SESET',
    ]);
    Department::factory()->create([
        'faculty_id' => $faculty->id,
        'name' => 'Computer Engineering',
        'abbreviation' => 'CPE',
    ]);
    AcademicSession::factory()->create([
        'school_id' => $school->id,
        'name' => '2025/2026',
        'starts_on' => '2025-09-01',
        'ends_on' => '2026-08-31',
    ]);
    $schoolAdmin = User::factory()->schoolAdmin()->create([
        'school_id' => $school->id,
        'name' => 'Okoro Chinyere',
        'email' => 'schooladmin@futo.edu',
    ]);

    $response = $this->actingAs($schoolAdmin)->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->where('dashboard.role', 'school_admin')
        ->where('dashboard.section', 'home')
        ->where('dashboard.roleLabel', 'School Admin')
        ->where('dashboard.schoolAdmin.school.abbreviation', 'FUTO')
        ->where('dashboard.schoolAdmin.metrics.faculties', 1)
        ->where('dashboard.schoolAdmin.metrics.departments', 1)
        ->where('dashboard.schoolAdmin.metrics.academicSessions', 1)
        ->where('dashboard.schoolAdmin.currentAcademicSession.name', '2025/2026')
        ->where('dashboard.schoolAdmin.faculties.0.abbreviation', 'SESET')
        ->where('dashboard.facultyHead', null)
        ->where('dashboard.staffHome', null)
        ->where('dashboard.studentHome', null)
        ->etc());
});

test('school admins manage school academic sessions', function () {
    $school = School::factory()->create();
    $schoolAdmin = User::factory()->schoolAdmin()->create([
        'school_id' => $school->id,
    ]);

    AcademicSession::factory()->create([
        'school_id' => $school->id,
        'name' => '2025/2026',
        'starts_on' => '2025-09-01',
        'ends_on' => '2026-08-31',
    ]);

    $created = $this->actingAs($schoolAdmin)->post(route('dashboard.academic-sessions.store'), [
        'name' => '2026/2027',
        'starts_on' => '2026-09-01',
        'ends_on' => '2027-08-31',
    ]);

    $created->assertRedirect(route('dashboard.section', [
        'section' => 'academic-sessions',
        'academic_session' => AcademicSession::query()->where('name', '2026/2027')->firstOrFail()->id,
    ]));
    $created->assertSessionHas('success', 'Academic session saved.');

    $overlapping = $this->actingAs($schoolAdmin)->post(route('dashboard.academic-sessions.store'), [
        'name' => 'Overlap',
        'starts_on' => '2026-01-01',
        'ends_on' => '2026-12-31',
    ]);

    $overlapping->assertSessionHasErrors('starts_on');
});

test('school admins create faculties and departments', function () {
    $school = School::factory()->create();
    $schoolAdmin = User::factory()->schoolAdmin()->create([
        'school_id' => $school->id,
    ]);

    $facultyResponse = $this->actingAs($schoolAdmin)->post(route('dashboard.structure.faculties.store'), [
        'name' => 'Electrical Systems and Engineering Technology',
        'abbreviation' => 'SESET',
    ]);

    $facultyResponse->assertRedirect(route('dashboard.section', [
        'section' => 'structure',
    ]));

    $faculty = Faculty::query()
        ->where('school_id', $school->id)
        ->where('abbreviation', 'SESET')
        ->firstOrFail();

    $departmentResponse = $this->actingAs($schoolAdmin)->post(route('dashboard.structure.departments.store'), [
        'faculty_id' => $faculty->id,
        'name' => 'Computer Engineering',
        'abbreviation' => 'CPE',
        'minimum_years_to_degree' => 5,
    ]);

    $departmentResponse->assertRedirect(route('dashboard.section', [
        'section' => 'structure',
    ]));

    expect($faculty->departments()->where('abbreviation', 'CPE')->firstOrFail()->minimum_years_to_degree)->toBe(5);
});

test('school admins provision faculty heads', function () {
    $school = School::factory()->create([
        'abbreviation' => 'FUTO',
    ]);
    $faculty = Faculty::factory()->create([
        'school_id' => $school->id,
        'abbreviation' => 'SESET',
    ]);
    $department = Department::factory()->create([
        'faculty_id' => $faculty->id,
        'abbreviation' => 'CPE',
    ]);
    $schoolAdmin = User::factory()->schoolAdmin()->create([
        'school_id' => $school->id,
    ]);

    $response = $this->actingAs($schoolAdmin)->post(route('dashboard.faculty-heads.store'), [
        'faculty_id' => $faculty->id,
        'department_id' => $department->id,
        'name' => 'Nwankwo Chika',
        'email' => 'facultyhead.seset@example.com',
        'access_code' => 'FUTO-SESET-HEAD',
    ]);

    $response->assertRedirect(route('dashboard.section', [
        'section' => 'faculty-heads',
    ]));
    $response->assertSessionHas('success', 'Faculty head saved.');

    $facultyHead = User::query()
        ->where('school_id', $school->id)
        ->where('email', 'facultyhead.seset@example.com')
        ->firstOrFail();

    expect($facultyHead->account_type)->toBe(AccountType::Staff)
        ->and($facultyHead->access_code)->toBe('FUTO-SESET-HEAD')
        ->and($facultyHead->activated_at)->toBeNull()
        ->and($facultyHead->staffProfile->department_id)->toBe($department->id)
        ->and($faculty->refresh()->head_staff_profile_id)->toBe($facultyHead->staffProfile->id)
        ->and(EmailDelivery::query()->where('user_id', $facultyHead->id)->where('status', 'skipped')->exists())->toBeTrue();
});

test('faculty heads can assign department staff as department heads with visible one time access codes', function () {
    $faculty = Faculty::factory()->create();
    $department = Department::factory()->create([
        'faculty_id' => $faculty->id,
        'name' => 'Computer Engineering',
        'abbreviation' => 'CPE',
    ]);
    $facultyHeadUser = User::factory()->staff()->create([
        'school_id' => $faculty->school_id,
    ]);
    $facultyHeadProfile = StaffProfile::factory()->create([
        'user_id' => $facultyHeadUser->id,
        'department_id' => $department->id,
    ]);

    $faculty->assignHead($facultyHeadProfile);

    $hodUser = User::factory()->staff()->pendingActivation('SESET-CPE-123456')->create([
        'school_id' => $faculty->school_id,
        'name' => 'Okafor Chinedu',
        'email' => 'hod.cpe@example.com',
    ]);
    $hodProfile = StaffProfile::factory()->create([
        'user_id' => $hodUser->id,
        'department_id' => $department->id,
    ]);

    $response = $this->actingAs($facultyHeadUser)->post(route('dashboard.hod-management.store'), [
        'department_id' => $department->id,
        'staff_profile_id' => $hodProfile->id,
    ]);

    $response->assertRedirect(route('dashboard.section', [
        'section' => 'departments',
    ]));

    $hodUser->refresh();

    expect($hodUser->account_type)->toBe(AccountType::Staff)
        ->and($hodUser->name)->toBe('Okafor Chinedu')
        ->and($hodUser->access_code)->toBe('SESET-CPE-123456')
        ->and($hodUser->access_code_used_at)->toBeNull()
        ->and($hodUser->activated_at)->toBeNull()
        ->and($department->refresh()->hod_staff_profile_id)->toBe($hodProfile->id);

    expect(EmailDelivery::query()
        ->where('user_id', $hodUser->id)
        ->where('recipient_email', 'hod.cpe@example.com')
        ->where('status', 'skipped')
        ->exists())->toBeTrue();
});

test('faculty heads can assign a staff row as hod of their department', function () {
    $faculty = Faculty::factory()->create();
    $department = Department::factory()->create([
        'faculty_id' => $faculty->id,
        'name' => 'Computer Engineering',
        'abbreviation' => 'CPE',
    ]);
    $facultyHeadUser = User::factory()->staff()->create([
        'school_id' => $faculty->school_id,
    ]);
    $facultyHeadProfile = StaffProfile::factory()->create([
        'user_id' => $facultyHeadUser->id,
        'department_id' => $department->id,
    ]);
    $staffUser = User::factory()->staff()->create([
        'school_id' => $faculty->school_id,
        'name' => 'Nneka Okorie',
    ]);
    $staffProfile = StaffProfile::factory()->create([
        'user_id' => $staffUser->id,
        'department_id' => $department->id,
    ]);

    $faculty->assignHead($facultyHeadProfile);

    $response = $this->actingAs($facultyHeadUser)->post(route('dashboard.hod-management.store'), [
        'department_id' => $department->id,
        'staff_profile_id' => $staffProfile->id,
        'redirect_section' => 'staff',
    ]);

    $response->assertRedirect(route('dashboard.section', [
        'section' => 'staff',
    ]));

    expect($department->refresh()->hod_staff_profile_id)->toBe($staffProfile->id);
});

test('faculty heads can only see faculty staff from the staff page', function () {
    $faculty = Faculty::factory()->create();
    $department = Department::factory()->create([
        'faculty_id' => $faculty->id,
        'abbreviation' => 'CPE',
    ]);
    $otherFaculty = Faculty::factory()->create([
        'school_id' => $faculty->school_id,
        'abbreviation' => 'SAAT',
    ]);
    $otherDepartment = Department::factory()->create([
        'faculty_id' => $otherFaculty->id,
        'abbreviation' => 'AGR',
    ]);
    $facultyHeadUser = User::factory()->staff()->create([
        'school_id' => $faculty->school_id,
        'name' => 'Ada Eze',
        'email' => 'ada.eze@example.com',
    ]);
    $facultyHeadProfile = StaffProfile::factory()->create([
        'user_id' => $facultyHeadUser->id,
        'department_id' => $department->id,
    ]);
    $facultyStaffUser = User::factory()->staff()->create([
        'school_id' => $faculty->school_id,
        'name' => 'Nneka Okorie',
        'email' => 'nneka.okorie@example.com',
    ]);
    $hodUser = User::factory()->staff()->create([
        'school_id' => $faculty->school_id,
        'name' => 'Ikenna Ude',
        'email' => 'ikenna.ude@example.com',
    ]);
    $otherStaffUser = User::factory()->staff()->create([
        'school_id' => $faculty->school_id,
        'name' => 'Chinedu Okafor',
        'email' => 'chinedu.okafor@example.com',
    ]);

    StaffProfile::factory()->create([
        'user_id' => $facultyStaffUser->id,
        'department_id' => $department->id,
    ]);
    $hodProfile = StaffProfile::factory()->create([
        'user_id' => $hodUser->id,
        'department_id' => $department->id,
    ]);

    StaffProfile::factory()->create([
        'user_id' => $otherStaffUser->id,
        'department_id' => $otherDepartment->id,
    ]);

    $faculty->assignHead($facultyHeadProfile);
    $department->assignHeadOfDepartment($hodProfile);

    $response = $this->actingAs($facultyHeadUser)->get(route('dashboard.section', [
        'section' => 'staff',
    ]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->where('dashboard.role', 'faculty_head')
        ->where('dashboard.section', 'staff')
        ->has('dashboard.facultyHead.staff', 3)
        ->where('dashboard.facultyHead.staff.0.email', 'ada.eze@example.com')
        ->where('dashboard.facultyHead.staff.0.role', 'Faculty Head')
        ->where('dashboard.facultyHead.staff.0.roleContext', $faculty->abbreviation)
        ->where('dashboard.facultyHead.staff.0.canAssignHod', false)
        ->where('dashboard.facultyHead.staff.0.assignmentBlockReason', 'Already Faculty Head')
        ->where('dashboard.facultyHead.staff.1.email', 'ikenna.ude@example.com')
        ->where('dashboard.facultyHead.staff.1.role', 'HOD')
        ->where('dashboard.facultyHead.staff.1.roleContext', 'CPE')
        ->where('dashboard.facultyHead.staff.1.department.id', $department->id)
        ->where('dashboard.facultyHead.staff.1.department.abbreviation', 'CPE')
        ->where('dashboard.facultyHead.staff.1.canAssignHod', false)
        ->where('dashboard.facultyHead.staff.1.assignmentBlockReason', 'Already HOD of CPE')
        ->where('dashboard.facultyHead.staff.2.email', 'nneka.okorie@example.com')
        ->where('dashboard.facultyHead.staff.2.department.id', $department->id)
        ->where('dashboard.facultyHead.staff.2.department.abbreviation', 'CPE')
        ->where('dashboard.facultyHead.staff.2.faculty.abbreviation', $faculty->abbreviation)
        ->where('dashboard.facultyHead.staff.2.roleContext', 'CPE')
        ->where('dashboard.facultyHead.staff.2.canAssignHod', true)
        ->where('dashboard.facultyHead.staff.2.assignmentBlockReason', null)
        ->etc());

    $formResponse = $this->actingAs($facultyHeadUser)->get(route('dashboard.departments.hod', [
        'department' => $department,
    ]));

    $formResponse->assertOk();
    $formResponse->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->where('dashboard.section', 'departments')
        ->where('dashboard.facultyHeadHodForm.department.abbreviation', 'CPE')
        ->where('dashboard.facultyHeadHodForm.department.currentHod.name', 'Ikenna Ude')
        ->has('dashboard.facultyHeadHodForm.staff', 3)
        ->where('dashboard.facultyHeadHodForm.staff.1.email', 'ikenna.ude@example.com')
        ->where('dashboard.facultyHeadHodForm.staff.1.canAssignHod', false)
        ->where('dashboard.facultyHeadHodForm.staff.1.assignmentBlockReason', 'Already HOD of CPE')
        ->etc());
});

test('staff profile routes use email and stay scoped to the viewer role', function () {
    $faculty = Faculty::factory()->create();
    $department = Department::factory()->create([
        'faculty_id' => $faculty->id,
        'abbreviation' => 'CPE',
    ]);
    $otherFaculty = Faculty::factory()->create([
        'school_id' => $faculty->school_id,
        'abbreviation' => 'SAAT',
    ]);
    $otherDepartment = Department::factory()->create([
        'faculty_id' => $otherFaculty->id,
        'abbreviation' => 'AGR',
    ]);
    $facultyHeadUser = User::factory()->staff()->create([
        'school_id' => $faculty->school_id,
        'email' => 'faculty.head@example.com',
    ]);
    $facultyHeadProfile = StaffProfile::factory()->create([
        'user_id' => $facultyHeadUser->id,
        'department_id' => $department->id,
    ]);
    $hodUser = User::factory()->staff()->create([
        'school_id' => $faculty->school_id,
        'email' => 'hod.cpe@example.com',
    ]);
    $hodProfile = StaffProfile::factory()->create([
        'user_id' => $hodUser->id,
        'department_id' => $department->id,
    ]);
    $staffUser = User::factory()->staff()->create([
        'school_id' => $faculty->school_id,
        'name' => 'Ada Eze',
        'email' => 'ada.eze@example.com',
    ]);
    StaffProfile::factory()->create([
        'user_id' => $staffUser->id,
        'department_id' => $department->id,
    ]);
    $outsideStaffUser = User::factory()->staff()->create([
        'school_id' => $faculty->school_id,
        'email' => 'outside.staff@example.com',
    ]);
    StaffProfile::factory()->create([
        'user_id' => $outsideStaffUser->id,
        'department_id' => $otherDepartment->id,
    ]);

    $faculty->assignHead($facultyHeadProfile);
    $department->assignHeadOfDepartment($hodProfile);

    $this->actingAs($facultyHeadUser)->get(route('dashboard.staff.show', [
        'staffEmail' => $staffUser->email,
    ]))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('dashboard.role', 'faculty_head')
            ->where('dashboard.section', 'staff')
            ->where('dashboard.staffRecord.staff.name', 'Ada Eze')
            ->where('dashboard.staffRecord.staff.email', 'ada.eze@example.com')
            ->where('dashboard.staffRecord.staff.department.abbreviation', 'CPE')
            ->etc());

    $this->actingAs($hodUser)->get(route('dashboard.staff.show', [
        'staffEmail' => $staffUser->email,
    ]))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('dashboard.role', 'hod')
            ->where('dashboard.section', 'staff')
            ->where('dashboard.staffRecord.staff.email', 'ada.eze@example.com')
            ->etc());

    $this->actingAs($hodUser)->get(route('dashboard.staff.show', [
        'staffEmail' => $outsideStaffUser->email,
    ]))->assertNotFound();

    $this->actingAs($facultyHeadUser)->get(route('dashboard.section', [
        'section' => 'staff',
    ]))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.facultyHead.staff', fn ($staff): bool => collect($staff)
                ->contains(fn (array $member): bool => $member['email'] === 'ada.eze@example.com'
                    && str_contains((string) $member['profileUrl'], '/dashboard/staff/ada.eze@example.com')))
            ->etc());
});

test('faculty heads cannot assign a hod outside their faculty', function () {
    $faculty = Faculty::factory()->create();
    $department = Department::factory()->create([
        'faculty_id' => $faculty->id,
    ]);
    $otherFaculty = Faculty::factory()->create([
        'school_id' => $faculty->school_id,
    ]);
    $otherDepartment = Department::factory()->create([
        'faculty_id' => $otherFaculty->id,
    ]);
    $facultyHeadUser = User::factory()->staff()->create([
        'school_id' => $faculty->school_id,
    ]);
    $facultyHeadProfile = StaffProfile::factory()->create([
        'user_id' => $facultyHeadUser->id,
        'department_id' => $department->id,
    ]);
    $staffUser = User::factory()->staff()->create([
        'school_id' => $faculty->school_id,
    ]);
    $staffProfile = StaffProfile::factory()->create([
        'user_id' => $staffUser->id,
        'department_id' => $department->id,
    ]);

    $faculty->assignHead($facultyHeadProfile);

    $this->actingAs($facultyHeadUser)->post(route('dashboard.hod-management.store'), [
        'department_id' => $otherDepartment->id,
        'staff_profile_id' => $staffProfile->id,
    ])->assertNotFound();
});

test('faculty heads cannot assign outside faculty staff as department heads', function () {
    $faculty = Faculty::factory()->create();
    $department = Department::factory()->create([
        'faculty_id' => $faculty->id,
    ]);
    $otherFaculty = Faculty::factory()->create([
        'school_id' => $faculty->school_id,
    ]);
    $otherDepartment = Department::factory()->create([
        'faculty_id' => $otherFaculty->id,
    ]);
    $facultyHeadUser = User::factory()->staff()->create([
        'school_id' => $faculty->school_id,
    ]);
    $facultyHeadProfile = StaffProfile::factory()->create([
        'user_id' => $facultyHeadUser->id,
        'department_id' => $department->id,
    ]);
    $outsideStaffUser = User::factory()->staff()->create([
        'school_id' => $faculty->school_id,
    ]);
    $outsideStaffProfile = StaffProfile::factory()->create([
        'user_id' => $outsideStaffUser->id,
        'department_id' => $otherDepartment->id,
    ]);

    $faculty->assignHead($facultyHeadProfile);

    $this->actingAs($facultyHeadUser)->post(route('dashboard.hod-management.store'), [
        'department_id' => $department->id,
        'staff_profile_id' => $outsideStaffProfile->id,
    ])->assertNotFound();
});

test('faculty heads cannot assign staff from another department as department hod', function () {
    $faculty = Faculty::factory()->create();
    $department = Department::factory()->create([
        'faculty_id' => $faculty->id,
    ]);
    $otherDepartment = Department::factory()->create([
        'faculty_id' => $faculty->id,
    ]);
    $facultyHeadUser = User::factory()->staff()->create([
        'school_id' => $faculty->school_id,
    ]);
    $facultyHeadProfile = StaffProfile::factory()->create([
        'user_id' => $facultyHeadUser->id,
        'department_id' => $department->id,
    ]);
    $otherDepartmentStaffUser = User::factory()->staff()->create([
        'school_id' => $faculty->school_id,
    ]);
    $otherDepartmentStaffProfile = StaffProfile::factory()->create([
        'user_id' => $otherDepartmentStaffUser->id,
        'department_id' => $otherDepartment->id,
    ]);

    $faculty->assignHead($facultyHeadProfile);

    $this->actingAs($facultyHeadUser)->post(route('dashboard.hod-management.store'), [
        'department_id' => $department->id,
        'staff_profile_id' => $otherDepartmentStaffProfile->id,
    ])->assertNotFound();
});

test('faculty heads can create faculty staff access codes', function () {
    $faculty = Faculty::factory()->create();
    $department = Department::factory()->create([
        'faculty_id' => $faculty->id,
        'name' => 'Computer Engineering',
        'abbreviation' => 'CPE',
    ]);
    $facultyHeadUser = User::factory()->staff()->create([
        'school_id' => $faculty->school_id,
    ]);
    $facultyHeadProfile = StaffProfile::factory()->create([
        'user_id' => $facultyHeadUser->id,
        'department_id' => $department->id,
    ]);

    $faculty->assignHead($facultyHeadProfile);

    $response = $this->actingAs($facultyHeadUser)->post(route('dashboard.staff.store'), [
        'department_id' => $department->id,
        'name' => 'Ifeoma Nwosu',
        'email' => 'ifeoma.nwosu@example.com',
        'access_code' => 'SESET-CPE-STAFF',
    ]);

    $response->assertRedirect(route('dashboard.section', [
        'section' => 'staff',
    ]));

    $staffUser = User::query()
        ->where('school_id', $faculty->school_id)
        ->where('email', 'ifeoma.nwosu@example.com')
        ->firstOrFail();

    expect($staffUser->account_type)->toBe(AccountType::Staff)
        ->and($staffUser->name)->toBe('Ifeoma Nwosu')
        ->and($staffUser->access_code)->toBe('SESET-CPE-STAFF')
        ->and($staffUser->access_code_used_at)->toBeNull()
        ->and($staffUser->activated_at)->toBeNull()
        ->and($staffUser->staffProfile->department_id)->toBe($department->id)
        ->and(EmailDelivery::query()
            ->where('user_id', $staffUser->id)
            ->where('recipient_email', 'ifeoma.nwosu@example.com')
            ->where('status', 'skipped')
            ->exists())->toBeTrue();
});

test('faculty heads can import faculty staff from spreadsheet rows', function () {
    $faculty = Faculty::factory()->create();
    $department = Department::factory()->create([
        'faculty_id' => $faculty->id,
        'name' => 'Computer Engineering',
        'abbreviation' => 'CPE',
    ]);
    $facultyHeadUser = User::factory()->staff()->create([
        'school_id' => $faculty->school_id,
    ]);
    $facultyHeadProfile = StaffProfile::factory()->create([
        'user_id' => $facultyHeadUser->id,
        'department_id' => $department->id,
    ]);

    $faculty->assignHead($facultyHeadProfile);

    $response = $this->actingAs($facultyHeadUser)->post(route('dashboard.staff.import.store'), [
        'file_name' => 'staff.xlsx',
        'rows' => [
            [
                'department' => 'CPE',
                'name' => 'Chika Obi',
                'email' => 'chika.obi@example.com',
                'accessCode' => 'SESET-CPE-BULK',
            ],
        ],
    ]);

    $response->assertRedirect(route('dashboard.section', [
        'section' => 'staff',
    ]));
    $response->assertSessionHas('success', 'Imported 1 staff record from spreadsheet.');

    $staffUser = User::query()
        ->where('school_id', $faculty->school_id)
        ->where('email', 'chika.obi@example.com')
        ->firstOrFail();

    expect($staffUser->account_type)->toBe(AccountType::Staff)
        ->and($staffUser->access_code)->toBe('SESET-CPE-BULK')
        ->and($staffUser->staffProfile->department_id)->toBe($department->id)
        ->and(ImportBatch::query()->where('file_name', 'staff.xlsx')->where('type', 'staff_import')->exists())->toBeTrue()
        ->and(EmailDelivery::query()->where('user_id', $staffUser->id)->where('status', 'skipped')->exists())->toBeTrue();
});

test('faculty heads can import faculty staff in frontend batches', function () {
    $faculty = Faculty::factory()->create();
    $department = Department::factory()->create([
        'faculty_id' => $faculty->id,
        'name' => 'Computer Engineering',
        'abbreviation' => 'CPE',
    ]);
    $facultyHeadUser = User::factory()->staff()->create([
        'school_id' => $faculty->school_id,
    ]);
    $facultyHeadProfile = StaffProfile::factory()->create([
        'user_id' => $facultyHeadUser->id,
        'department_id' => $department->id,
    ]);

    $faculty->assignHead($facultyHeadProfile);

    $firstBatch = $this->actingAs($facultyHeadUser)->post(route('dashboard.staff.import.store'), [
        'file_name' => 'staff.xlsx',
        'import_key' => 'faculty-staff-import-1',
        'batch_number' => 1,
        'total_batches' => 2,
        'start_row' => 1,
        'total_rows' => 2,
        'batch_size' => 1,
        'rows' => [
            [
                'department' => 'CPE',
                'name' => 'Chika Obi',
                'email' => 'chika.obi@example.com',
                'accessCode' => 'SESET-CPE-BULK-1',
            ],
        ],
    ]);

    $firstBatch->assertRedirect(route('dashboard.staff.import'));
    $firstBatch->assertSessionHas('success', 'Imported batch 1 of 2. Continue with the next batch.');

    $secondBatch = $this->actingAs($facultyHeadUser)->post(route('dashboard.staff.import.store'), [
        'file_name' => 'staff.xlsx',
        'import_key' => 'faculty-staff-import-1',
        'batch_number' => 2,
        'total_batches' => 2,
        'start_row' => 2,
        'total_rows' => 2,
        'batch_size' => 1,
        'rows' => [
            [
                'department' => 'CPE',
                'name' => 'Ada Umeh',
                'email' => 'ada.umeh@example.com',
                'accessCode' => 'SESET-CPE-BULK-2',
            ],
        ],
    ]);

    $secondBatch->assertRedirect(route('dashboard.section', [
        'section' => 'staff',
    ]));
    $secondBatch->assertSessionHas('success', 'Imported 2 staff records from spreadsheet.');

    $importBatch = ImportBatch::query()
        ->where('import_key', 'faculty-staff-import-1')
        ->where('type', 'staff_import')
        ->firstOrFail();

    expect($importBatch->summary)->toMatchArray([
        'submitted_rows' => 2,
        'total_batches' => 2,
        'processed_batches' => 2,
        'imported_staff' => 2,
    ])
        ->and($importBatch->rows()->orderBy('row_number')->pluck('row_number')->all())->toBe([1, 2])
        ->and(User::query()->where('school_id', $faculty->school_id)->whereIn('email', [
            'chika.obi@example.com',
            'ada.umeh@example.com',
        ])->count())->toBe(2);
});

test('faculty staff spreadsheet imports generate missing access codes', function () {
    $faculty = Faculty::factory()->create([
        'abbreviation' => 'SESET',
    ]);
    $department = Department::factory()->create([
        'faculty_id' => $faculty->id,
        'name' => 'Computer Engineering',
        'abbreviation' => 'CPE',
    ]);
    $facultyHeadUser = User::factory()->staff()->create([
        'school_id' => $faculty->school_id,
    ]);
    $facultyHeadProfile = StaffProfile::factory()->create([
        'user_id' => $facultyHeadUser->id,
        'department_id' => $department->id,
    ]);

    $faculty->assignHead($facultyHeadProfile);

    $response = $this->actingAs($facultyHeadUser)->post(route('dashboard.staff.import.store'), [
        'file_name' => 'staff.xlsx',
        'rows' => [
            [
                'department' => 'CPE',
                'name' => 'Obinna Eze',
                'email' => 'obinna.eze@example.com',
                'accessCode' => '',
            ],
        ],
    ]);

    $response->assertRedirect(route('dashboard.section', [
        'section' => 'staff',
    ]));

    $staffUser = User::query()
        ->where('school_id', $faculty->school_id)
        ->where('email', 'obinna.eze@example.com')
        ->firstOrFail();

    expect($staffUser->access_code)->toStartWith('SESET-CPE-')
        ->and($staffUser->access_code)->not->toBe('SESET-CPE-')
        ->and($staffUser->access_code_used_at)->toBeNull()
        ->and($staffUser->activated_at)->toBeNull()
        ->and(EmailDelivery::query()
            ->where('user_id', $staffUser->id)
            ->where('metadata->access_code', $staffUser->access_code)
            ->exists())->toBeTrue();
});

test('department heads can open their staff spreadsheet import page', function () {
    $department = Department::factory()->create([
        'name' => 'Computer Engineering',
        'abbreviation' => 'CPE',
    ]);
    $department->load('faculty');
    $hodUser = User::factory()->staff()->create([
        'school_id' => $department->faculty->school_id,
    ]);
    $hodProfile = StaffProfile::factory()->create([
        'user_id' => $hodUser->id,
        'department_id' => $department->id,
    ]);

    $department->assignHeadOfDepartment($hodProfile);

    $this->actingAs($hodUser)->get(route('dashboard.department-staff.import'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('dashboard.role', 'hod')
            ->where('dashboard.section', 'staff')
            ->where('dashboard.hodStaffForm.mode', 'import')
            ->where('dashboard.hodStaffForm.department.abbreviation', 'CPE')
            ->etc());
});

test('department heads can import staffs for their department', function () {
    $faculty = Faculty::factory()->create([
        'abbreviation' => 'SESET',
    ]);
    $department = Department::factory()->create([
        'faculty_id' => $faculty->id,
        'name' => 'Computer Engineering',
        'abbreviation' => 'CPE',
    ]);
    $hodUser = User::factory()->staff()->create([
        'school_id' => $faculty->school_id,
    ]);
    $hodProfile = StaffProfile::factory()->create([
        'user_id' => $hodUser->id,
        'department_id' => $department->id,
    ]);

    $department->assignHeadOfDepartment($hodProfile);

    $response = $this->actingAs($hodUser)->post(route('dashboard.department-staff.import.store'), [
        'file_name' => 'cpe-staff.xlsx',
        'rows' => [
            [
                'name' => 'Ifeoma Nwosu',
                'email' => 'ifeoma.nwosu@example.com',
                'accessCode' => '',
            ],
        ],
    ]);

    $response->assertRedirect(route('dashboard.section', [
        'section' => 'staff',
    ]));
    $response->assertSessionHas('success', 'Imported 1 staff record from spreadsheet.');

    $staffUser = User::query()
        ->where('school_id', $faculty->school_id)
        ->where('email', 'ifeoma.nwosu@example.com')
        ->firstOrFail();

    expect($staffUser->account_type)->toBe(AccountType::Staff)
        ->and($staffUser->name)->toBe('Ifeoma Nwosu')
        ->and($staffUser->access_code)->toStartWith('SESET-CPE-')
        ->and($staffUser->staffProfile->department_id)->toBe($department->id)
        ->and(ImportBatch::query()
            ->where('file_name', 'cpe-staff.xlsx')
            ->where('type', 'hod_staff_import')
            ->where('scope_type', Department::class)
            ->where('scope_id', $department->id)
            ->exists())->toBeTrue()
        ->and(EmailDelivery::query()
            ->where('user_id', $staffUser->id)
            ->where('metadata->access_code', $staffUser->access_code)
            ->exists())->toBeTrue();
});

test('department heads cannot import existing staff from another department', function () {
    $faculty = Faculty::factory()->create();
    $department = Department::factory()->create([
        'faculty_id' => $faculty->id,
        'abbreviation' => 'CPE',
    ]);
    $otherDepartment = Department::factory()->create([
        'faculty_id' => $faculty->id,
        'abbreviation' => 'EEE',
    ]);
    $hodUser = User::factory()->staff()->create([
        'school_id' => $faculty->school_id,
    ]);
    $hodProfile = StaffProfile::factory()->create([
        'user_id' => $hodUser->id,
        'department_id' => $department->id,
    ]);
    $existingStaff = User::factory()->staff()->create([
        'school_id' => $faculty->school_id,
        'email' => 'staff.eee@example.com',
    ]);

    StaffProfile::factory()->create([
        'user_id' => $existingStaff->id,
        'department_id' => $otherDepartment->id,
    ]);

    $department->assignHeadOfDepartment($hodProfile);

    $this->actingAs($hodUser)->from(route('dashboard.department-staff.import'))
        ->post(route('dashboard.department-staff.import.store'), [
            'file_name' => 'cpe-staff.xlsx',
            'rows' => [
                [
                    'name' => 'Existing Staff',
                    'email' => 'staff.eee@example.com',
                    'accessCode' => '',
                ],
            ],
        ])
        ->assertRedirect(route('dashboard.department-staff.import'))
        ->assertSessionHasErrors([
            'rows.0.email' => 'This staff member already belongs to EEE.',
        ]);

    expect($existingStaff->staffProfile->refresh()->department_id)->toBe($otherDepartment->id);
});

test('faculty heads can import hods from spreadsheet rows', function () {
    $faculty = Faculty::factory()->create();
    $department = Department::factory()->create([
        'faculty_id' => $faculty->id,
        'name' => 'Computer Engineering',
        'abbreviation' => 'CPE',
    ]);
    $facultyHeadUser = User::factory()->staff()->create([
        'school_id' => $faculty->school_id,
    ]);
    $facultyHeadProfile = StaffProfile::factory()->create([
        'user_id' => $facultyHeadUser->id,
        'department_id' => $department->id,
    ]);

    $faculty->assignHead($facultyHeadProfile);

    $response = $this->actingAs($facultyHeadUser)->post(route('dashboard.hod-management.import.store'), [
        'file_name' => 'hods.xlsx',
        'rows' => [
            [
                'department' => 'CPE',
                'name' => 'Nkem Okorie',
                'email' => 'nkem.okorie@example.com',
                'accessCode' => 'SESET-CPE-IMPORT',
            ],
        ],
    ]);

    $response->assertRedirect(route('dashboard.section', [
        'section' => 'departments',
    ]));
    $response->assertSessionHas('success', 'Imported 1 HOD assignment from spreadsheet.');

    $hodUser = User::query()
        ->where('school_id', $faculty->school_id)
        ->where('email', 'nkem.okorie@example.com')
        ->firstOrFail();

    expect($hodUser->account_type)->toBe(AccountType::Staff)
        ->and($hodUser->access_code)->toBe('SESET-CPE-IMPORT')
        ->and($hodUser->staffProfile->department_id)->toBe($department->id)
        ->and($department->refresh()->hod_staff_profile_id)->toBe($hodUser->staffProfile->id)
        ->and(ImportBatch::query()->where('file_name', 'hods.xlsx')->where('type', 'hod_import')->exists())->toBeTrue()
        ->and(EmailDelivery::query()->where('user_id', $hodUser->id)->where('status', 'skipped')->exists())->toBeTrue();
});

test('staff users see the staff dashboard shell', function () {
    $user = User::factory()->staff()->create();

    StaffProfile::factory()->create([
        'user_id' => $user->id,
    ]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->where('dashboard.role', 'staff')
        ->where('dashboard.section', 'home')
        ->where('dashboard.roleLabel', 'Staff')
        ->where('dashboard.staffHome.totalStudents', 0)
        ->has('dashboard.staffHome.assignedSets', 0)
        ->where('dashboard.studentHome', null)
        ->etc());
});

test('staff users can update their profile settings password', function () {
    $user = User::factory()->staff()->create([
        'password' => 'old-password',
    ]);

    StaffProfile::factory()->create([
        'user_id' => $user->id,
    ]);

    $response = $this->actingAs($user)->patch(route('dashboard.settings.staff-password.update'), [
        'current_password' => 'old-password',
        'password' => 'new-secure-password',
        'password_confirmation' => 'new-secure-password',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success', 'Password changed.');

    expect(Hash::check('new-secure-password', $user->refresh()->password))->toBeTrue();
});

test('staff can review approve and return course registrations for assigned sets', function () {
    $this->seed(AcademicStructureSeeder::class);

    $department = Department::factory()->create();
    $department->load('faculty.school');
    $school = $department->faculty->school;
    $level = AcademicYear::query()->where('slug', '300-level')->firstOrFail();
    $semester = Semester::query()->where('slug', 'harmattan')->firstOrFail();
    $yearSemester = AcademicYearSemester::query()
        ->where('academic_year_id', $level->id)
        ->where('semester_id', $semester->id)
        ->firstOrFail();
    $entrySession = AcademicSession::factory()->create([
        'school_id' => $school->id,
        'name' => '2023/2024',
        'starts_on' => '2023-09-01',
        'ends_on' => '2024-08-31',
    ]);
    $academicSession = AcademicSession::factory()->create([
        'school_id' => $school->id,
        'name' => '2025/2026',
        'starts_on' => '2025-09-01',
        'ends_on' => '2026-08-31',
    ]);
    $staffUser = User::factory()->staff()->create([
        'school_id' => $school->id,
        'name' => 'Ada Eze',
    ]);
    $staffProfile = StaffProfile::factory()->create([
        'user_id' => $staffUser->id,
        'department_id' => $department->id,
    ]);
    $department->assignAdvisorToStudentSet($entrySession, $staffProfile, $academicSession);
    $studentUser = User::factory()->student()->create([
        'school_id' => $school->id,
        'name' => 'Chidera Okafor',
    ]);
    $studentProfile = StudentProfile::factory()->create([
        'school_id' => $school->id,
        'user_id' => $studentUser->id,
        'department_id' => $department->id,
        'current_academic_year_id' => $level->id,
        'entry_academic_session_id' => $entrySession->id,
        'registration_number' => '2023001002',
    ]);
    $course = Course::factory()->create([
        'school_id' => $school->id,
        'code' => 'CPE 301',
        'title' => 'Computer Architecture',
        'slug' => 'cpe-301-computer-architecture',
        'credit_units' => 3,
    ]);
    $offering = CourseOffering::factory()->create([
        'owner_type' => Department::class,
        'owner_id' => $department->id,
        'course_id' => $course->id,
        'academic_session_id' => $academicSession->id,
        'academic_year_semester_id' => $yearSemester->id,
        'category' => CourseCategory::Departmental,
    ]);
    $registration = CourseRegistration::query()->create([
        'school_id' => $school->id,
        'department_id' => $department->id,
        'student_profile_id' => $studentProfile->id,
        'academic_session_id' => $academicSession->id,
        'academic_year_semester_id' => $yearSemester->id,
        'reviewer_staff_profile_id' => $staffProfile->id,
        'status' => CourseRegistration::StatusSubmitted,
        'submitted_at' => now(),
    ]);
    CourseRegistrationItem::query()->create([
        'course_registration_id' => $registration->id,
        'course_offering_id' => $offering->id,
        'source' => CourseRegistrationItem::SourceCurrentSet,
        'category' => 'Departmental Course',
        'course_code' => 'CPE 301',
        'course_title' => 'Computer Architecture',
        'credit_units' => 3,
    ]);

    $this->actingAs($staffUser)->get(route('dashboard.section', [
        'section' => 'course-registration',
    ]))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('dashboard.role', 'staff')
            ->where('dashboard.section', 'course-registration')
            ->where('dashboard.sectionTitle', 'Course Registrations')
            ->has('dashboard.staffCourseRegistration.registrations', 1)
            ->where('dashboard.staffCourseRegistration.registrations.0.student.registrationNumber', '2023001002')
            ->where('dashboard.staffCourseRegistration.registrations.0.status', 'In review')
            ->etc());

    $this->actingAs($staffUser)->get(route('dashboard.course-registration.show', [
        'courseRegistration' => $registration,
    ]))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('dashboard.role', 'staff')
            ->where('dashboard.sectionTitle', 'Course Registrations')
            ->where('dashboard.staffCourseRegistration.review.student.registrationNumber', '2023001002')
            ->where('dashboard.staffCourseRegistration.review.canReview', true)
            ->where('dashboard.staffCourseRegistration.review.courses.0.code', 'CPE 301')
            ->where('dashboard.staffCourseRegistration.review.courses.0.sourceLabel', 'Current Set')
            ->etc());

    $approveResponse = $this->actingAs($staffUser)->patch(route('dashboard.course-registration.approve', [
        'courseRegistration' => $registration,
    ]));

    $approveResponse->assertRedirect(route('dashboard.course-registration.show', [
        'courseRegistration' => $registration,
    ]));
    $approveResponse->assertSessionHas('success', 'Course registration approved.');
    expect($registration->refresh()->status)->toBe(CourseRegistration::StatusApproved);

    $registration->forceFill([
        'status' => CourseRegistration::StatusSubmitted,
        'feedback_note' => null,
        'reviewed_at' => null,
    ])->save();

    $returnResponse = $this->actingAs($staffUser)->patch(route('dashboard.course-registration.return', [
        'courseRegistration' => $registration,
    ]), [
        'feedback_note' => 'Remove the extra course and submit again.',
    ]);

    $returnResponse->assertRedirect(route('dashboard.course-registration.show', [
        'courseRegistration' => $registration,
    ]));
    $returnResponse->assertSessionHas('success', 'Course registration returned to the student.');
    expect($registration->refresh()->status)->toBe(CourseRegistration::StatusReturned)
        ->and($registration->feedback_note)->toBe('Remove the extra course and submit again.');
});

test('staff home is scoped to assigned student sets', function () {
    $this->seed(AcademicStructureSeeder::class);

    $department = Department::factory()->create();
    $department->load('faculty.school');
    $school = $department->faculty->school;
    $assignedLevel = AcademicYear::query()->where('slug', '300-level')->firstOrFail();
    $currentSession = AcademicSession::factory()->create([
        'school_id' => $school->id,
        'name' => '2025/2026',
        'starts_on' => '2025-09-01',
        'ends_on' => '2026-08-31',
    ]);
    $assignedEntrySession = AcademicSession::factory()->create([
        'school_id' => $school->id,
        'name' => '2023/2024',
        'starts_on' => '2023-09-01',
        'ends_on' => '2024-08-31',
    ]);
    AcademicSession::factory()->create([
        'school_id' => $school->id,
        'name' => '2024/2025',
        'starts_on' => '2024-09-01',
        'ends_on' => '2025-08-31',
    ]);
    $otherEntrySession = AcademicSession::factory()->create([
        'school_id' => $school->id,
        'name' => '2022/2023',
        'starts_on' => '2022-09-01',
        'ends_on' => '2023-08-31',
    ]);
    $user = User::factory()->staff()->create([
        'school_id' => $school->id,
    ]);
    $staffProfile = StaffProfile::factory()->create([
        'user_id' => $user->id,
        'department_id' => $department->id,
    ]);

    $department->assignAdvisorToStudentSet($assignedEntrySession, $staffProfile, $currentSession);

    $assignedStudents = collect();

    foreach ([
        ['name' => 'Ada Nwosu', 'registration_number' => '2023001001'],
        ['name' => 'Chidera Eze', 'registration_number' => '2023001002'],
    ] as $student) {
        $studentUser = User::factory()->student()->create([
            'school_id' => $school->id,
            'name' => $student['name'],
        ]);

        $assignedStudents->push(StudentProfile::factory()->create([
            'school_id' => $school->id,
            'user_id' => $studentUser->id,
            'department_id' => $department->id,
            'current_academic_year_id' => $assignedLevel->id,
            'entry_academic_session_id' => $assignedEntrySession->id,
            'registration_number' => $student['registration_number'],
        ]));
    }

    $outsideUser = User::factory()->student()->create([
        'school_id' => $school->id,
        'name' => 'Ifeoma Obi',
    ]);

    StudentProfile::factory()->create([
        'school_id' => $school->id,
        'user_id' => $outsideUser->id,
        'department_id' => $department->id,
        'current_academic_year_id' => $assignedLevel->id,
        'entry_academic_session_id' => $otherEntrySession->id,
        'registration_number' => '2022001003',
    ]);

    $departmentDue = createTestDue($department, $assignedEntrySession, $currentSession, 'Departmental Dues 300 Level', 1500000, $staffProfile);
    $associationDue = createTestDue($department, $assignedEntrySession, $currentSession, 'Student Association Levy 300 Level', 500000, $staffProfile);
    $facultyDue = createTestDue($department, $assignedEntrySession, $currentSession, 'Faculty Development Levy', 800000, $staffProfile);

    $assignedStudents->each(function (StudentProfile $studentProfile) use ($departmentDue, $associationDue, $facultyDue): void {
        assignTestDue($departmentDue, $studentProfile);
        assignTestDue($associationDue, $studentProfile);
        markTestDuePaid(assignTestDue($facultyDue, $studentProfile), '2026-05-04 10:00:00');
    });

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->where('dashboard.role', 'staff')
        ->where('dashboard.section', 'home')
        ->has('dashboard.staffHome', fn (Assert $page) => $page
            ->has('assignedSets', 1)
            ->where('assignedSets.0.entrySession.name', '2023/2024')
            ->has('levels', 5)
            ->has('students', 2)
            ->where('students.0.fullName', 'Ada Nwosu')
            ->where('students.0.registrationNumber', '2023001001')
            ->where('totalStudents', 2)
            ->where('maximumCourseUnits', 24)
            ->where('studentsWithCarryovers', 0)
            ->has('pendingDuesByStudent', 2)
            ->where('pendingDuesByStudent.0.studentName', 'Ada Nwosu')
            ->where('pendingDuesByStudent.0.registrationNumber', '2023001001')
            ->has('pendingDuesByStudent.0.dues', 2)
            ->where('pendingDuesByStudent.0.dues.0', 'Departmental Dues 300 Level')
            ->where('pendingDuesByStudent.0.outstandingAmount', 'NGN 20,000')
            ->has('recentPayments', 2)
            ->where('recentPayments.0.studentName', 'Ada Nwosu')
            ->where('recentPayments.0.dueName', 'Faculty Development Levy')
            ->where('recentPayments.0.amount', 'NGN 8,000')
            ->has('carryoverSummaries', 0)
        )
        ->where('dashboard.studentHome', null)
        ->etc());
});

test('staff can publish a due for an assigned student set', function () {
    $this->seed(AcademicStructureSeeder::class);

    $department = Department::factory()->create();
    $department->load('faculty.school');
    $school = $department->faculty->school;
    $assignedLevel = AcademicYear::query()->where('slug', '300-level')->firstOrFail();
    $currentSession = AcademicSession::factory()->create([
        'school_id' => $school->id,
        'name' => '2025/2026',
        'starts_on' => '2025-09-01',
        'ends_on' => '2026-08-31',
    ]);
    $assignedEntrySession = AcademicSession::factory()->create([
        'school_id' => $school->id,
        'name' => '2023/2024',
        'starts_on' => '2023-09-01',
        'ends_on' => '2024-08-31',
    ]);
    $otherEntrySession = AcademicSession::factory()->create([
        'school_id' => $school->id,
        'name' => '2022/2023',
        'starts_on' => '2022-09-01',
        'ends_on' => '2023-08-31',
    ]);
    $user = User::factory()->staff()->create([
        'school_id' => $school->id,
    ]);
    $staffProfile = StaffProfile::factory()->create([
        'user_id' => $user->id,
        'department_id' => $department->id,
    ]);

    $department->assignAdvisorToStudentSet($assignedEntrySession, $staffProfile, $currentSession);
    $paymentAccount = createTestPaymentAccount($department, $staffProfile, $assignedEntrySession);

    foreach ([
        ['name' => 'Ada Nwosu', 'registration_number' => '2023001001', 'entry_session_id' => $assignedEntrySession->id],
        ['name' => 'Chidera Eze', 'registration_number' => '2023001002', 'entry_session_id' => $assignedEntrySession->id],
        ['name' => 'Ifeoma Obi', 'registration_number' => '2022001003', 'entry_session_id' => $otherEntrySession->id],
    ] as $student) {
        $studentUser = User::factory()->student()->create([
            'school_id' => $school->id,
            'name' => $student['name'],
        ]);

        StudentProfile::factory()->create([
            'school_id' => $school->id,
            'user_id' => $studentUser->id,
            'department_id' => $department->id,
            'current_academic_year_id' => $assignedLevel->id,
            'entry_academic_session_id' => $student['entry_session_id'],
            'registration_number' => $student['registration_number'],
        ]);
    }

    $response = $this->actingAs($user)->post(route('dashboard.dues.store'), [
        'entry_academic_session_id' => $assignedEntrySession->id,
        'title' => 'Departmental Dues 300 Level',
        'description' => 'Department levy.',
        'amount_naira' => 15000,
        'due_on' => '2026-06-30',
        'action' => 'publish',
    ]);

    $due = Due::query()->where('title', 'Departmental Dues 300 Level')->firstOrFail();

    $response->assertRedirect(route('dashboard.dues.show', ['due' => $due]));
    $response->assertSessionHas('success', 'Due published for the selected set.');

    expect($due->status)->toBe(Due::StatusPublished)
        ->and($due->created_by_staff_profile_id)->toBe($staffProfile->id)
        ->and($due->payment_account_id)->toBe($paymentAccount->id)
        ->and($due->amount_kobo)->toBe(1500000)
        ->and($due->assignments()->count())->toBe(2);

    $this->actingAs($user)->get(route('dashboard.section', [
        'section' => 'dues',
    ]))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->has('dashboard.staffDues', fn (Assert $page) => $page
                ->where('metrics.expectedAmount', 'NGN 30,000')
                ->where('metrics.outstandingAmount', 'NGN 30,000')
                ->where('metrics.studentsOwing', 2)
                ->has('dues', 1)
                ->where('dues.0.title', 'Departmental Dues 300 Level')
                ->where('dues.0.paidCount', 0)
                ->where('dues.0.pendingCount', 2)
                ->etc()
            )
            ->etc());

    $this->actingAs($user)->get(route('dashboard.dues.show', ['due' => $due]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->has('dashboard.staffDueDetail', fn (Assert $page) => $page
                ->where('due.title', 'Departmental Dues 300 Level')
                ->has('students', 2)
                ->where('students.0.studentName', 'Ada Nwosu')
                ->where('students.0.status', 'Pending')
            )
            ->etc());
});

test('staff cannot publish a due until a payout account is connected', function () {
    $this->seed(AcademicStructureSeeder::class);

    $department = Department::factory()->create();
    $department->load('faculty.school');
    $school = $department->faculty->school;
    $currentSession = AcademicSession::factory()->create([
        'school_id' => $school->id,
        'name' => '2025/2026',
        'starts_on' => '2025-09-01',
        'ends_on' => '2026-08-31',
    ]);
    $assignedEntrySession = AcademicSession::factory()->create([
        'school_id' => $school->id,
        'name' => '2023/2024',
        'starts_on' => '2023-09-01',
        'ends_on' => '2024-08-31',
    ]);
    $user = User::factory()->staff()->create([
        'school_id' => $school->id,
    ]);
    $staffProfile = StaffProfile::factory()->create([
        'user_id' => $user->id,
        'department_id' => $department->id,
    ]);

    $department->assignAdvisorToStudentSet($assignedEntrySession, $staffProfile, $currentSession);

    $response = $this->actingAs($user)->post(route('dashboard.dues.store'), [
        'entry_academic_session_id' => $assignedEntrySession->id,
        'title' => 'Departmental Dues 300 Level',
        'description' => 'Department levy.',
        'amount_naira' => 15000,
        'due_on' => '2026-06-30',
        'action' => 'publish',
    ]);

    $response->assertSessionHasErrors('payment_account');

    expect(Due::query()->where('title', 'Departmental Dues 300 Level')->exists())->toBeFalse();
});

test('staff Paystack settings shows connected accounts and change link', function () {
    $this->seed(AcademicStructureSeeder::class);

    Config::set('inertia.ssr.enabled', false);

    $department = Department::factory()->create();
    $department->load('faculty.school');
    $school = $department->faculty->school;
    $user = User::factory()->staff()->create([
        'school_id' => $school->id,
    ]);
    $staffProfile = StaffProfile::factory()->create([
        'user_id' => $user->id,
        'department_id' => $department->id,
    ]);

    PaymentAccount::factory()->create([
        'school_id' => $school->id,
        'department_id' => $department->id,
        'staff_profile_id' => $staffProfile->id,
        'entry_academic_session_id' => null,
        'status' => PaymentAccount::StatusActive,
        'label' => 'Student dues account',
    ]);

    $this->actingAs($user)->get(route('dashboard.section', [
        'section' => 'payment-settings',
    ]))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('dashboard.section', 'payment-settings')
            ->where('dashboard.staffPaymentSettings.accounts.0.label', 'Student dues account')
            ->where('dashboard.staffPaymentSettings.accounts.0.accountNumber', '0123456789')
            ->where('dashboard.staffPaymentSettings.createUrl', route('dashboard.payment-settings.create'))
            ->where('dashboard.staffPaymentAccountForm', null)
            ->etc());
});

test('staff Paystack account form loads banks from Paystack', function () {
    $this->seed(AcademicStructureSeeder::class);

    Cache::forget(PaystackService::NigerianBankCacheKey);
    Config::set('inertia.ssr.enabled', false);
    Config::set('services.paystack.secret_key', 'sk_test_grade_manager');
    Http::preventStrayRequests();
    Http::fake([
        'https://api.paystack.co/bank*' => Http::response([
            'status' => true,
            'data' => [
                ['code' => '999001', 'name' => 'Test Staff Bank'],
                ['code' => '999002', 'name' => 'Another Test Bank'],
            ],
            'meta' => ['next' => null],
        ]),
    ]);

    $department = Department::factory()->create();
    $department->load('faculty.school');
    $school = $department->faculty->school;
    $user = User::factory()->staff()->create([
        'school_id' => $school->id,
    ]);

    StaffProfile::factory()->create([
        'user_id' => $user->id,
        'department_id' => $department->id,
    ]);

    $this->actingAs($user)->get(route('dashboard.payment-settings.create'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('dashboard.section', 'payment-settings')
            ->where('dashboard.staffPaymentSettings.accounts', [])
            ->where('dashboard.staffPaymentAccountForm.bankOptions.0.name', 'Another Test Bank')
            ->where('dashboard.staffPaymentAccountForm.bankOptions.1.name', 'Test Staff Bank')
            ->where('dashboard.staffPaymentAccountForm.bankOptionsError', null)
            ->where('dashboard.staffPaymentAccountForm.returnUrl', route('dashboard.section', ['section' => 'payment-settings']))
            ->etc());

    Http::assertSent(fn ($request): bool => str_starts_with($request->url(), 'https://api.paystack.co/bank')
        && $request->data()['country'] === 'nigeria');
});

test('staff can connect one Paystack payout account for all their dues', function () {
    $this->seed(AcademicStructureSeeder::class);

    Cache::forget(PaystackService::NigerianBankCacheKey);
    Config::set('services.paystack.secret_key', 'sk_test_grade_manager');
    Http::preventStrayRequests();
    Http::fake([
        'https://api.paystack.co/bank/resolve*' => Http::response([
            'status' => true,
            'data' => [
                'account_number' => '0123456789',
                'account_name' => 'Ada Eze',
                'bank_id' => 9,
            ],
        ]),
        'https://api.paystack.co/subaccount' => Http::response([
            'status' => true,
            'data' => [
                'subaccount_code' => 'ACCT_staff_dues',
                'business_name' => 'CPE 300L Dues',
            ],
        ]),
        'https://api.paystack.co/bank*' => Http::response([
            'status' => true,
            'data' => [
                ['code' => '058', 'name' => 'Paystack Returned Bank'],
            ],
            'meta' => ['next' => null],
        ]),
    ]);

    $department = Department::factory()->create();
    $department->load('faculty.school');
    $school = $department->faculty->school;
    $currentSession = AcademicSession::factory()->create([
        'school_id' => $school->id,
        'name' => '2025/2026',
        'starts_on' => '2025-09-01',
        'ends_on' => '2026-08-31',
    ]);
    $assignedEntrySession = AcademicSession::factory()->create([
        'school_id' => $school->id,
        'name' => '2023/2024',
        'starts_on' => '2023-09-01',
        'ends_on' => '2024-08-31',
    ]);
    $user = User::factory()->staff()->create([
        'school_id' => $school->id,
        'name' => 'Ada Eze',
    ]);
    $staffProfile = StaffProfile::factory()->create([
        'user_id' => $user->id,
        'department_id' => $department->id,
    ]);

    $department->assignAdvisorToStudentSet($assignedEntrySession, $staffProfile, $currentSession);

    $response = $this->actingAs($user)->post(route('dashboard.payment-settings.store'), [
        'label' => 'Student dues payout',
        'business_name' => 'CPE Student Dues',
        'bank_code' => '058',
        'bank_name' => 'Browser Submitted Bank',
        'account_number' => '0123456789',
    ]);

    $response->assertRedirect(route('dashboard.section', [
        'section' => 'payment-settings',
    ]));
    $response->assertSessionHas('success', 'Payout account saved. New payments for all your dues will use this account.');

    Http::assertSent(fn ($request): bool => $request->url() === 'https://api.paystack.co/subaccount'
        && $request->data()['business_name'] === 'CPE Student Dues'
        && $request->data()['bank_code'] === '058'
        && ! array_key_exists('settlement_bank', $request->data())
        && $request->data()['account_number'] === '0123456789'
        && $request->data()['percentage_charge'] === 0);

    $paymentAccount = PaymentAccount::query()->firstOrFail();

    expect($paymentAccount->status)->toBe(PaymentAccount::StatusActive)
        ->and($paymentAccount->entry_academic_session_id)->toBeNull()
        ->and($paymentAccount->bank_name)->toBe('Paystack Returned Bank')
        ->and($paymentAccount->account_name)->toBe('Ada Eze')
        ->and($paymentAccount->account_number_last4)->toBe('6789')
        ->and($paymentAccount->paystack_subaccount_code)->toBe('ACCT_staff_dues')
        ->and($paymentAccount->provider_payload)->not->toHaveKeys(['resolved_account', 'subaccount']);
});

test('changing payout details updates the advisers current account instead of adding another account', function () {
    $this->seed(AcademicStructureSeeder::class);

    Cache::forget(PaystackService::NigerianBankCacheKey);
    Config::set('services.paystack.secret_key', 'sk_test_grade_manager');
    Http::preventStrayRequests();
    Http::fake([
        'https://api.paystack.co/bank/resolve*' => Http::response([
            'status' => true,
            'data' => [
                'account_number' => '1111111111',
                'account_name' => 'Ada Eze',
            ],
        ]),
        'https://api.paystack.co/subaccount/ACCT_existing' => Http::response([
            'status' => true,
            'data' => [
                'id' => 27,
                'subaccount_code' => 'ACCT_existing',
                'business_name' => 'CPE Student Dues',
                'domain' => 'test',
                'active' => true,
            ],
        ]),
        'https://api.paystack.co/bank*' => Http::response([
            'status' => true,
            'data' => [
                ['code' => '058', 'name' => 'Guaranty Trust Bank'],
            ],
            'meta' => ['next' => null],
        ]),
    ]);

    $department = Department::factory()->create();
    $department->load('faculty.school');
    $school = $department->faculty->school;
    $user = User::factory()->staff()->create([
        'school_id' => $school->id,
        'name' => 'Ada Eze',
    ]);
    $staffProfile = StaffProfile::factory()->create([
        'user_id' => $user->id,
        'department_id' => $department->id,
    ]);
    $paymentAccount = createTestPaymentAccount($department, $staffProfile);
    $paymentAccount->forceFill([
        'paystack_subaccount_code' => 'ACCT_existing',
    ])->save();

    $this->actingAs($user)
        ->post(route('dashboard.payment-settings.store'), [
            'label' => 'Student dues payout',
            'business_name' => 'CPE Student Dues',
            'bank_code' => '058',
            'account_number' => '1111111111',
        ])
        ->assertRedirect(route('dashboard.section', ['section' => 'payment-settings']))
        ->assertSessionHas(
            'success',
            'Payout account saved. New payments for all your dues will use this account.',
        );

    expect(PaymentAccount::query()->where('staff_profile_id', $staffProfile->id)->count())->toBe(1)
        ->and($paymentAccount->refresh()->account_number)->toBe('1111111111')
        ->and($paymentAccount->account_name)->toBe('Ada Eze')
        ->and($paymentAccount->paystack_subaccount_code)->toBe('ACCT_existing');

    Http::assertSent(fn ($request): bool => $request->method() === 'PUT'
        && $request->url() === 'https://api.paystack.co/subaccount/ACCT_existing'
        && $request->data()['account_number'] === '1111111111');
});

test('staff students page lists assigned students alphabetically', function () {
    $this->seed(AcademicStructureSeeder::class);

    $department = Department::factory()->create();
    $department->load('faculty.school');
    $school = $department->faculty->school;
    $assignedLevel = AcademicYear::query()->where('slug', '300-level')->firstOrFail();
    $currentSession = AcademicSession::factory()->create([
        'school_id' => $school->id,
        'name' => '2025/2026',
        'starts_on' => '2025-09-01',
        'ends_on' => '2026-08-31',
    ]);
    $assignedEntrySession = AcademicSession::factory()->create([
        'school_id' => $school->id,
        'name' => '2023/2024',
        'starts_on' => '2023-09-01',
        'ends_on' => '2024-08-31',
    ]);
    AcademicSession::factory()->create([
        'school_id' => $school->id,
        'name' => '2024/2025',
        'starts_on' => '2024-09-01',
        'ends_on' => '2025-08-31',
    ]);
    $otherEntrySession = AcademicSession::factory()->create([
        'school_id' => $school->id,
        'name' => '2022/2023',
        'starts_on' => '2022-09-01',
        'ends_on' => '2023-08-31',
    ]);
    $user = User::factory()->staff()->create([
        'school_id' => $school->id,
    ]);
    $staffProfile = StaffProfile::factory()->create([
        'user_id' => $user->id,
        'department_id' => $department->id,
    ]);

    $department->assignAdvisorToStudentSet($assignedEntrySession, $staffProfile, $currentSession);

    foreach ([
        ['name' => 'Chidera Eze', 'email' => 'chidera.eze@example.com', 'registration_number' => '2023001002'],
        ['name' => 'Ada Nwosu', 'email' => 'ada.nwosu@example.com', 'registration_number' => '2023001001'],
    ] as $student) {
        $studentUser = User::factory()->student()->create([
            'school_id' => $school->id,
            'name' => $student['name'],
            'email' => $student['email'],
        ]);

        StudentProfile::factory()->create([
            'school_id' => $school->id,
            'user_id' => $studentUser->id,
            'department_id' => $department->id,
            'current_academic_year_id' => $assignedLevel->id,
            'entry_academic_session_id' => $assignedEntrySession->id,
            'registration_number' => $student['registration_number'],
        ]);
    }

    $outsideUser = User::factory()->student()->create([
        'school_id' => $school->id,
        'name' => 'Ifeoma Obi',
    ]);

    StudentProfile::factory()->create([
        'school_id' => $school->id,
        'user_id' => $outsideUser->id,
        'department_id' => $department->id,
        'current_academic_year_id' => $assignedLevel->id,
        'entry_academic_session_id' => $otherEntrySession->id,
        'registration_number' => '2022001003',
    ]);

    $response = $this->actingAs($user)->get(route('dashboard.section', [
        'section' => 'students',
    ]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->where('dashboard.role', 'staff')
        ->where('dashboard.section', 'students')
        ->where('dashboard.staffHome', null)
        ->has('dashboard.staffStudents', fn (Assert $page) => $page
            ->where('department.abbreviation', $department->abbreviation)
            ->has('assignedSets', 1)
            ->where('assignedSets.0.entrySession.name', '2023/2024')
            ->where('totalStudents', 2)
            ->has('students', 2)
            ->where('students.0.fullName', 'Ada Nwosu')
            ->where('students.0.email', 'ada.nwosu@example.com')
            ->where('students.0.registrationNumber', '2023001001')
            ->where('students.0.currentLevel.title', '300 Level')
            ->where('students.0.profileUrl', fn (string $url): bool => str_contains($url, '/dashboard/students/2023001001'))
            ->where('students.0.pendingCarryovers', [])
            ->has('students.0.levelGpas', 3)
            ->where('students.0.levelGpas.0.level', '100L')
            ->where('students.0.cgpa', null)
            ->where('students.1.fullName', 'Chidera Eze')
            ->etc()
        )
        ->where('dashboard.studentHome', null)
        ->etc());
});

test('course advisers can provision a student for an assigned set', function () {
    $this->seed(AcademicStructureSeeder::class);

    $department = Department::factory()->create();
    $department->load('faculty.school');
    $school = $department->faculty->school;
    $currentSession = AcademicSession::factory()->create([
        'school_id' => $school->id,
        'name' => '2025/2026',
        'starts_on' => '2025-09-01',
        'ends_on' => '2026-08-31',
    ]);
    $entrySession = AcademicSession::factory()->create([
        'school_id' => $school->id,
        'name' => '2023/2024',
        'starts_on' => '2023-09-01',
        'ends_on' => '2024-08-31',
    ]);
    $staffUser = User::factory()->staff()->create(['school_id' => $school->id]);
    $staffProfile = StaffProfile::factory()->create([
        'user_id' => $staffUser->id,
        'department_id' => $department->id,
    ]);

    $department->assignAdvisorToStudentSet($entrySession, $staffProfile, $currentSession);

    $this->actingAs($staffUser)
        ->get(route('dashboard.students.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('dashboard.staffStudentForm.mode', 'create')
            ->where('dashboard.staffStudentForm.assignedSets.0.entrySession.id', $entrySession->id)
            ->etc());

    $response = $this->actingAs($staffUser)->post(route('dashboard.students.store'), [
        'entry_academic_session_id' => $entrySession->id,
        'registration_number' => '2023004001',
        'access_code' => 'CPE-STUDENT-ONE',
    ]);

    $response->assertRedirect(route('dashboard.section', ['section' => 'students']));
    $response->assertSessionHasNoErrors();

    $studentProfile = StudentProfile::query()
        ->where('school_id', $school->id)
        ->where('registration_number', '2023004001')
        ->firstOrFail();

    expect($studentProfile->department_id)->toBe($department->id)
        ->and($studentProfile->entry_academic_session_id)->toBe($entrySession->id)
        ->and($studentProfile->user->account_type)->toBe(AccountType::Student)
        ->and($studentProfile->user->status)->toBe(AccountStatus::Pending)
        ->and($studentProfile->user->access_code)->toBe('CPE-STUDENT-ONE')
        ->and($studentProfile->user->email)->toBeNull();
});

test('course advisers can import students in auditable spreadsheet batches', function () {
    $this->seed(AcademicStructureSeeder::class);

    $department = Department::factory()->create();
    $department->load('faculty.school');
    $school = $department->faculty->school;
    $currentSession = AcademicSession::factory()->create([
        'school_id' => $school->id,
        'name' => '2025/2026',
        'starts_on' => '2025-09-01',
        'ends_on' => '2026-08-31',
    ]);
    $entrySession = AcademicSession::factory()->create([
        'school_id' => $school->id,
        'name' => '2023/2024',
        'starts_on' => '2023-09-01',
        'ends_on' => '2024-08-31',
    ]);
    $staffUser = User::factory()->staff()->create(['school_id' => $school->id]);
    $staffProfile = StaffProfile::factory()->create([
        'user_id' => $staffUser->id,
        'department_id' => $department->id,
    ]);

    $department->assignAdvisorToStudentSet($entrySession, $staffProfile, $currentSession);

    $response = $this->actingAs($staffUser)->post(route('dashboard.students.import.store'), [
        'entry_academic_session_id' => $entrySession->id,
        'file_name' => 'student-classlist.xlsx',
        'import_key' => 'student-import-test',
        'batch_number' => 1,
        'total_batches' => 1,
        'start_row' => 1,
        'total_rows' => 2,
        'mappings' => [
            'registrationNumber' => 'Registration Number',
            'accessCode' => 'Access Code',
        ],
        'rows' => [
            ['registrationNumber' => '2023004101', 'accessCode' => 'CPE-STUDENT-A'],
            ['registrationNumber' => '2023004102', 'accessCode' => ''],
        ],
    ]);

    $response->assertRedirect(route('dashboard.section', ['section' => 'students']));
    $response->assertSessionHasNoErrors();

    $importBatch = ImportBatch::query()->where('import_key', 'student-import-test')->firstOrFail();
    $students = StudentProfile::query()
        ->with('user')
        ->where('school_id', $school->id)
        ->whereIn('registration_number', ['2023004101', '2023004102'])
        ->orderBy('registration_number')
        ->get();

    expect($importBatch->mappings)->toBe([
        'registrationNumber' => 'Registration Number',
        'accessCode' => 'Access Code',
    ])->and($importBatch->rows()->count())->toBe(2)
        ->and($students)->toHaveCount(2)
        ->and($students[0]->user->access_code)->toBe('CPE-STUDENT-A')
        ->and($students[1]->user->access_code)->toStartWith($department->abbreviation.'-STUDENT-');
});

test('department heads see the hod dashboard shell', function () {
    $department = Department::factory()->create();
    $department->load('faculty');
    $user = User::factory()->staff()->create([
        'school_id' => $department->faculty->school_id,
    ]);
    $staffProfile = StaffProfile::factory()->create([
        'user_id' => $user->id,
        'department_id' => $department->id,
    ]);

    $department->assignHeadOfDepartment($staffProfile);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->where('dashboard.role', 'hod')
        ->where('dashboard.section', 'home')
        ->where('dashboard.roleLabel', 'Head of Department')
        ->where('dashboard.staffHome', null)
        ->where('dashboard.hod.department.name', $department->name)
        ->where('dashboard.hod.metrics.students', 0)
        ->where('dashboard.hod.staff.0.role', 'HOD')
        ->where('dashboard.hod.staff.0.roleContext', $department->abbreviation)
        ->etc());
});

test('department heads use student results instead of reports', function () {
    $department = Department::factory()->create();
    $user = User::factory()->staff()->create();
    $staffProfile = StaffProfile::factory()->create([
        'user_id' => $user->id,
        'department_id' => $department->id,
    ]);

    $department->assignHeadOfDepartment($staffProfile);

    $this->actingAs($user)->get(route('dashboard.section', [
        'section' => 'student-results',
    ]))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('dashboard.role', 'hod')
            ->where('dashboard.section', 'student-results')
            ->where('dashboard.sectionTitle', 'Student Results')
            ->has('dashboard.hod.studentResults.resultSheets', 0)
            ->has('dashboard.hod.studentResults.approvalSheets', 0)
            ->etc());

    $this->actingAs($user)->get(route('dashboard.section', [
        'section' => 'reports',
    ]))->assertNotFound();
});

test('result sheet detail is limited to assigned staff and department heads', function () {
    $this->seed(AcademicStructureSeeder::class);

    $department = Department::factory()->create();
    $department->load('faculty.school');
    $entrySession = AcademicSession::factory()->create([
        'school_id' => $department->faculty->school_id,
        'name' => '2023/2024',
        'starts_on' => '2023-09-01',
        'ends_on' => '2024-08-31',
    ]);
    $academicSession = AcademicSession::factory()->create([
        'school_id' => $department->faculty->school_id,
        'name' => '2025/2026',
        'starts_on' => '2025-09-01',
        'ends_on' => '2026-08-31',
    ]);
    $yearSemester = AcademicYearSemester::query()
        ->where('academic_year_id', 3)
        ->whereHas('semester', fn ($query) => $query->where('slug', 'harmattan'))
        ->firstOrFail();
    $staffUser = User::factory()->staff()->create([
        'school_id' => $department->faculty->school_id,
        'name' => 'Ada Eze',
    ]);
    $staffProfile = StaffProfile::factory()->create([
        'user_id' => $staffUser->id,
        'department_id' => $department->id,
    ]);
    $outsideStaffUser = User::factory()->staff()->create([
        'school_id' => $department->faculty->school_id,
    ]);

    StaffProfile::factory()->create([
        'user_id' => $outsideStaffUser->id,
        'department_id' => $department->id,
    ]);
    StudentSetAdvisorAssignment::factory()->create([
        'department_id' => $department->id,
        'entry_academic_session_id' => $entrySession->id,
        'staff_profile_id' => $staffProfile->id,
    ]);

    $resultSheet = ResultSheet::query()->create([
        'school_id' => $department->faculty->school_id,
        'department_id' => $department->id,
        'entry_academic_session_id' => $entrySession->id,
        'academic_session_id' => $academicSession->id,
        'academic_year_semester_id' => $yearSemester->id,
        'created_by_user_id' => $staffUser->id,
        'submitted_by_user_id' => $staffUser->id,
        'status' => ResultSheet::StatusSubmitted,
        'submitted_at' => now(),
    ]);
    $rainYearSemester = AcademicYearSemester::query()
        ->where('academic_year_id', 3)
        ->whereHas('semester', fn ($query) => $query->where('slug', 'rain'))
        ->firstOrFail();
    $approvedSheet = ResultSheet::query()->create([
        'school_id' => $department->faculty->school_id,
        'department_id' => $department->id,
        'entry_academic_session_id' => $entrySession->id,
        'academic_session_id' => $academicSession->id,
        'academic_year_semester_id' => $rainYearSemester->id,
        'created_by_user_id' => $staffUser->id,
        'submitted_by_user_id' => $staffUser->id,
        'reviewed_by_user_id' => $staffUser->id,
        'status' => ResultSheet::StatusApproved,
        'submitted_at' => now(),
        'approved_at' => now(),
    ]);

    $this->actingAs($staffUser)->get(route('dashboard.results.show', $resultSheet))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('dashboard.role', 'staff')
            ->where('dashboard.section', 'result-management')
            ->where('dashboard.resultSheetDetail.sheet.id', $resultSheet->id)
            ->where('dashboard.resultSheetDetail.mode', 'view')
            ->where('dashboard.resultSheetDetail.canReview', false)
            ->where('dashboard.resultSheetDetail.canEdit', false)
            ->where('dashboard.resultSheetDetail.sheet.statusLabel', 'Awaiting HOD Review')
            ->where('dashboard.resultSheetDetail.sheet.submittedBy.name', 'Ada Eze')
            ->where('dashboard.resultSheetDetail.sheet.submittedBy.email', $staffUser->email)
            ->where('dashboard.resultSheetDetail.sheet.submittedBy.profileUrl', route('dashboard.staff.show', ['staffEmail' => $staffUser->email]))
            ->etc());

    $this->actingAs($outsideStaffUser)->get(route('dashboard.results.show', $resultSheet))
        ->assertNotFound();

    $hodUser = User::factory()->staff()->create([
        'school_id' => $department->faculty->school_id,
    ]);
    $hodProfile = StaffProfile::factory()->create([
        'user_id' => $hodUser->id,
        'department_id' => $department->id,
    ]);

    $department->assignHeadOfDepartment($hodProfile);

    $this->actingAs($hodUser)->get(route('dashboard.section', [
        'section' => 'student-results',
    ]))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('dashboard.role', 'hod')
            ->where('dashboard.section', 'student-results')
            ->has('dashboard.hod.studentResults.resultSheets', 1)
            ->where('dashboard.hod.studentResults.resultSheets.0.id', $approvedSheet->id)
            ->has('dashboard.hod.studentResults.approvalSheets', 1)
            ->where('dashboard.hod.studentResults.approvalSheets.0.id', $resultSheet->id)
            ->etc());

    $this->actingAs($hodUser)->get(route('dashboard.results.show', $resultSheet))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('dashboard.role', 'hod')
            ->where('dashboard.section', 'student-results')
            ->where('dashboard.resultSheetDetail.sheet.id', $resultSheet->id)
            ->where('dashboard.resultSheetDetail.mode', 'view')
            ->where('dashboard.resultSheetDetail.canReview', false)
            ->where('dashboard.resultSheetDetail.canEdit', false)
            ->etc());

    $this->actingAs($hodUser)->get(route('dashboard.result-approvals.show', $resultSheet))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('dashboard.role', 'hod')
            ->where('dashboard.section', 'result-approvals')
            ->where('dashboard.resultSheetDetail.sheet.id', $resultSheet->id)
            ->where('dashboard.resultSheetDetail.mode', 'review')
            ->where('dashboard.resultSheetDetail.canReview', true)
            ->where('dashboard.resultSheetDetail.canEdit', false)
            ->etc());

    $this->actingAs($hodUser)->patch(route('dashboard.results.review', $resultSheet), [
        'action' => 'return',
        'correction_reason' => 'incomplete_data',
    ])->assertRedirect(route('dashboard.section', ['section' => 'result-approvals']));

    $resultSheet->refresh();

    expect($resultSheet->status)->toBe(ResultSheet::StatusReturned)
        ->and($resultSheet->correction_note)->toBe('Incomplete data')
        ->and($resultSheet->current_revision_number)->toBe(1)
        ->and($resultSheet->revisions()->value('action'))->toBe('returned_for_correction')
        ->and($resultSheet->revisions()->value('actor_user_id'))->toBe($hodUser->id);
});

test('result import stores regular and carried courses in one semester sheet', function () {
    $this->seed(AcademicStructureSeeder::class);
    Storage::fake('local');

    $department = Department::factory()->create();
    $department->load('faculty.school');
    $schoolId = $department->faculty->school_id;
    $entrySession = AcademicSession::factory()->create([
        'school_id' => $schoolId,
        'name' => '2023/2024',
        'starts_on' => '2023-09-01',
        'ends_on' => '2024-08-31',
    ]);
    AcademicSession::factory()->create([
        'school_id' => $schoolId,
        'name' => '2024/2025',
        'starts_on' => '2024-09-01',
        'ends_on' => '2025-08-31',
    ]);
    $academicSession = AcademicSession::factory()->create([
        'school_id' => $schoolId,
        'name' => '2025/2026',
        'starts_on' => '2025-09-01',
        'ends_on' => '2026-08-31',
    ]);
    $level = AcademicYear::query()->where('slug', '300-level')->firstOrFail();
    $yearSemester = AcademicYearSemester::query()
        ->where('academic_year_id', $level->id)
        ->whereHas('semester', fn ($query) => $query->where('slug', 'harmattan'))
        ->firstOrFail();
    $carryoverLevel = AcademicYear::query()->where('slug', '200-level')->firstOrFail();
    $carryoverYearSemester = AcademicYearSemester::query()
        ->where('academic_year_id', $carryoverLevel->id)
        ->whereHas('semester', fn ($query) => $query->where('slug', 'rain'))
        ->firstOrFail();
    $staffUser = User::factory()->staff()->create(['school_id' => $schoolId]);
    $staffProfile = StaffProfile::factory()->create([
        'user_id' => $staffUser->id,
        'department_id' => $department->id,
    ]);

    StudentSetAdvisorAssignment::factory()->create([
        'department_id' => $department->id,
        'entry_academic_session_id' => $entrySession->id,
        'staff_profile_id' => $staffProfile->id,
    ]);
    $regularCourse = Course::factory()->create([
        'school_id' => $schoolId,
        'code' => 'CPE 301',
        'title' => 'Computer Architecture',
        'slug' => 'cpe-301-computer-architecture',
        'credit_units' => 3,
    ]);

    $regularOffering = CourseOffering::factory()->create([
        'owner_type' => Department::class,
        'owner_id' => $department->id,
        'course_id' => $regularCourse->id,
        'academic_session_id' => $academicSession->id,
        'academic_year_semester_id' => $yearSemester->id,
    ]);
    $carryoverCourse = Course::factory()->create([
        'school_id' => $schoolId,
        'code' => 'CPE 204',
        'title' => 'Signals and Systems',
        'slug' => 'cpe-204-signals-and-systems',
        'credit_units' => 3,
    ]);

    $carryoverOffering = CourseOffering::factory()->create([
        'owner_type' => Department::class,
        'owner_id' => $department->id,
        'course_id' => $carryoverCourse->id,
        'academic_session_id' => $academicSession->id,
        'academic_year_semester_id' => $carryoverYearSemester->id,
    ]);

    $studentOne = StudentProfile::factory()->create([
        'school_id' => $schoolId,
        'department_id' => $department->id,
        'entry_academic_session_id' => $entrySession->id,
        'current_academic_year_id' => $level->id,
        'registration_number' => '2023000001',
    ]);
    $studentTwo = StudentProfile::factory()->create([
        'school_id' => $schoolId,
        'department_id' => $department->id,
        'entry_academic_session_id' => $entrySession->id,
        'current_academic_year_id' => $level->id,
        'registration_number' => '2023000002',
    ]);

    $importer = app(ResultSheetImporter::class);
    $payload = [
        'entry_academic_session_id' => $entrySession->id,
        'academic_year_semester_id' => $yearSemester->id,
        'action' => 'draft',
        'source_file' => UploadedFile::fake()->create(
            '300-level-harmattan-results.xlsx',
            24,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ),
        'column_mappings' => [
            'registration_number' => 'Registration Number',
            'CPE 301' => 'Computer Architecture',
        ],
        'courses' => [
            ['course_offering_id' => $regularOffering->id, 'code' => 'CPE 301', 'title' => 'Computer Architecture', 'credit_units' => 3],
            ['course_offering_id' => $carryoverOffering->id, 'code' => 'CPE 204', 'title' => 'Signals and Systems', 'credit_units' => 3],
        ],
        'rows' => [
            [
                'row_number' => 1,
                'registration_number' => $studentOne->registration_number,
                'grades' => ['CPE 301' => 'A', 'CPE 204' => 'B'],
            ],
            [
                'row_number' => 2,
                'registration_number' => $studentTwo->registration_number,
                'grades' => ['CPE 301' => 'C', 'CPE 204' => null],
            ],
        ],
    ];

    $sheet = $importer->import($department, $staffProfile, $payload);
    $sheet->load('courses', 'studentResults.courseResults.resultSheetCourse');
    $firstRevision = $sheet->revisions()->firstOrFail();

    expect(ResultSheet::query()
        ->where('department_id', $department->id)
        ->where('entry_academic_session_id', $entrySession->id)
        ->where('academic_session_id', $academicSession->id)
        ->where('academic_year_semester_id', $yearSemester->id)
        ->count())->toBe(1)
        ->and($sheet->courses)->toHaveCount(2)
        ->and($sheet->studentResults)->toHaveCount(2)
        ->and($sheet->studentResults->firstWhere('student_profile_id', $studentOne->id)?->courseResults)->toHaveCount(2)
        ->and($sheet->studentResults->firstWhere('student_profile_id', $studentTwo->id)?->courseResults)->toHaveCount(1)
        ->and($sheet->current_revision_number)->toBe(1)
        ->and($firstRevision->action)->toBe('draft_saved')
        ->and($firstRevision->source_file_checksum)->not->toBeNull()
        ->and($firstRevision->column_mappings['registration_number'])->toBe('Registration Number')
        ->and($firstRevision->snapshot['students'])->toHaveCount(2);

    Storage::disk('local')->assertExists($firstRevision->source_file_path);

    $this->actingAs($staffUser)
        ->get(route('dashboard.results.source-file', $sheet))
        ->assertOk()
        ->assertDownload('300-level-harmattan-results.xlsx');

    $this->actingAs($staffUser)
        ->get(route('dashboard.results.show', ['resultSheet' => $sheet]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.resultSheetDetail.sheet.courses.0.code', 'CPE 301')
            ->where('dashboard.resultSheetDetail.sheet.courses.0.isCarryover', false)
            ->where('dashboard.resultSheetDetail.sheet.courses.1.code', 'CPE 204')
            ->where('dashboard.resultSheetDetail.sheet.courses.1.isCarryover', true)
            ->etc());

    $updated = $importer->import($department, $staffProfile, [
        ...$payload,
        'action' => 'submit',
        'source_file' => null,
        'rows' => [
            [
                'row_number' => 1,
                'registration_number' => $studentOne->registration_number,
                'grades' => ['CPE 301' => 'B', 'CPE 204' => 'A'],
            ],
        ],
    ]);

    expect($updated->id)->toBe($sheet->id)
        ->and($updated->status)->toBe(ResultSheet::StatusSubmitted)
        ->and($updated->current_revision_number)->toBe(2)
        ->and($updated->revisions()->count())->toBe(2)
        ->and($updated->revisions()->orderBy('revision_number')->pluck('action')->all())->toBe([
            'draft_saved',
            'submitted',
        ])
        ->and(ResultSheet::query()
            ->where('department_id', $department->id)
            ->where('entry_academic_session_id', $entrySession->id)
            ->where('academic_session_id', $academicSession->id)
            ->where('academic_year_semester_id', $yearSemester->id)
            ->count())->toBe(1);

    $updated->forceFill([
        'status' => ResultSheet::StatusApproved,
        'approved_at' => now(),
    ])->save();

    $export = $this->actingAs($staffUser)->get(route('dashboard.students.statement', [
        'registrationNumber' => $studentOne->registration_number,
    ]));

    $export->assertOk()
        ->assertDownload('statement-of-results-2023000001.csv');

    expect($export->streamedContent())
        ->toContain('Statement of Results')
        ->toContain('2023000001')
        ->toContain('CPE 301')
        ->toContain('Computer Architecture');
});

test('department heads can view student profiles in their department', function () {
    $this->seed(AcademicStructureSeeder::class);

    $department = Department::factory()->create();
    $department->load('faculty.school');
    $currentLevel = AcademicYear::query()->where('slug', '300-level')->firstOrFail();
    $entrySession = AcademicSession::factory()->create([
        'school_id' => $department->faculty->school_id,
        'name' => '2023/2024',
    ]);
    $hodUser = User::factory()->staff()->create([
        'school_id' => $department->faculty->school_id,
    ]);
    $hodProfile = StaffProfile::factory()->create([
        'user_id' => $hodUser->id,
        'department_id' => $department->id,
    ]);
    $studentUser = User::factory()->student()->create([
        'school_id' => $department->faculty->school_id,
        'name' => 'Ada Nwosu',
        'email' => 'ada.nwosu@example.com',
    ]);
    $studentProfile = StudentProfile::factory()->create([
        'school_id' => $department->faculty->school_id,
        'user_id' => $studentUser->id,
        'department_id' => $department->id,
        'current_academic_year_id' => $currentLevel->id,
        'entry_academic_session_id' => $entrySession->id,
        'registration_number' => '2023001001',
    ]);
    $outsideProfile = StudentProfile::factory()->create([
        'current_academic_year_id' => $currentLevel->id,
        'registration_number' => '2023001002',
    ]);

    $department->assignHeadOfDepartment($hodProfile);

    $this->actingAs($hodUser)->get(route('dashboard.students.show', [
        'registrationNumber' => $studentProfile->registration_number,
    ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('dashboard.role', 'hod')
            ->where('dashboard.section', 'students')
            ->where('dashboard.studentRecord.student.fullName', 'Ada Nwosu')
            ->where('dashboard.studentRecord.student.registrationNumber', '2023001001')
            ->where('dashboard.studentRecord.entrySession.name', '2023/2024')
            ->has('dashboard.studentRecord.semesters', 0)
            ->etc());

    $this->actingAs($hodUser)->get(route('dashboard.students.show', [
        'registrationNumber' => $outsideProfile->registration_number,
    ]))
        ->assertNotFound();
});

test('dashboard section routes render placeholders for allowed roles', function () {
    $this->seed(AcademicStructureSeeder::class);

    $user = User::factory()->staff()->create();

    StaffProfile::factory()->create([
        'user_id' => $user->id,
    ]);

    $response = $this->actingAs($user)->get(route('dashboard.section', [
        'section' => 'courses',
        'level' => '100-level',
    ]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->where('dashboard.role', 'staff')
        ->where('dashboard.section', 'courses')
        ->where('dashboard.level', null)
        ->etc());

    $this->actingAs($user)->get(route('dashboard.section', [
        'section' => 'approvals',
    ]))->assertNotFound();
});

test('dashboard routes reject sections outside the current role', function () {
    $user = User::factory()->student()->create();

    StudentProfile::factory()->create([
        'user_id' => $user->id,
    ]);

    $response = $this->actingAs($user)->get(route('dashboard.section', [
        'section' => 'students',
    ]));

    $response->assertNotFound();
});

test('users can logout from the dashboard', function () {
    $user = User::factory()->student()->create();

    $response = $this->actingAs($user)->post(route('logout'));

    $response->assertRedirect(route('home'));
    $this->assertGuest();
});

test('departments replace the current hod when a new staff profile is assigned', function () {
    $department = Department::factory()->create();
    $firstProfile = StaffProfile::factory()->create([
        'department_id' => $department->id,
    ]);
    $secondProfile = StaffProfile::factory()->create([
        'department_id' => $department->id,
    ]);

    $department->assignHeadOfDepartment($firstProfile);

    expect($department->hod_staff_profile_id)->toBe($firstProfile->id);

    $department->assignHeadOfDepartment($secondProfile);

    expect($department->hod_staff_profile_id)->toBe($secondProfile->id);
});

test('course advisers manage the full due lifecycle and can update pending amounts', function () {
    $this->seed(AcademicStructureSeeder::class);

    $department = Department::factory()->create();
    $department->load('faculty.school');
    $school = $department->faculty->school;
    $entrySession = AcademicSession::factory()->create([
        'school_id' => $school->id,
        'name' => '2023/2024',
        'starts_on' => '2023-09-01',
        'ends_on' => '2024-08-31',
    ]);
    AcademicSession::factory()->create([
        'school_id' => $school->id,
        'name' => '2025/2026',
        'starts_on' => '2025-09-01',
        'ends_on' => '2026-08-31',
    ]);
    $staffUser = User::factory()->staff()->create(['school_id' => $school->id]);
    $staffProfile = StaffProfile::factory()->create([
        'user_id' => $staffUser->id,
        'department_id' => $department->id,
    ]);
    StudentSetAdvisorAssignment::factory()->create([
        'department_id' => $department->id,
        'entry_academic_session_id' => $entrySession->id,
        'staff_profile_id' => $staffProfile->id,
    ]);
    createTestPaymentAccount($department, $staffProfile, $entrySession);
    $currentLevel = AcademicYear::query()->where('slug', '300-level')->firstOrFail();
    $studentProfile = StudentProfile::factory()->create([
        'school_id' => $school->id,
        'department_id' => $department->id,
        'entry_academic_session_id' => $entrySession->id,
        'current_academic_year_id' => $currentLevel->id,
    ]);
    $payload = [
        'entry_academic_session_id' => $entrySession->id,
        'title' => 'Departmental Dues 300 Level',
        'description' => 'Department levy.',
        'amount_naira' => 15000,
        'due_on' => '2026-07-31',
    ];

    $this->actingAs($staffUser)
        ->post(route('dashboard.dues.store'), [...$payload, 'action' => 'draft'])
        ->assertRedirect();

    $due = Due::query()->latest('id')->firstOrFail();

    expect($due->status)->toBe(Due::StatusDraft)
        ->and($due->assignments()->count())->toBe(0);

    $this->actingAs($staffUser)
        ->patch(route('dashboard.dues.update', $due), [...$payload, 'action' => 'publish'])
        ->assertRedirect(route('dashboard.dues.show', $due));

    $assignment = $due->assignments()->where('student_profile_id', $studentProfile->id)->firstOrFail();

    expect($due->refresh()->status)->toBe(Due::StatusPublished)
        ->and($assignment->status)->toBe(StudentDueAssignment::StatusPending);

    $openCheckout = DuePayment::query()->create([
        'student_due_assignment_id' => $assignment->id,
        'student_profile_id' => $assignment->student_profile_id,
        'school_id' => $school->id,
        'payment_account_id' => $due->payment_account_id,
        'provider' => DuePayment::ProviderPaystack,
        'reference' => 'DUE-AMOUNT-CHANGE-TEST',
        'amount_kobo' => 1500000,
        'currency' => 'NGN',
        'status' => DuePayment::StatusInitialized,
    ]);

    $this->actingAs($staffUser)
        ->patch(route('dashboard.dues.update', $due), [
            ...$payload,
            'amount_naira' => 16000,
            'action' => 'draft',
        ])
        ->assertRedirect(route('dashboard.dues.show', $due));

    expect($due->refresh()->amount_kobo)->toBe(1600000)
        ->and($assignment->refresh()->amount_kobo)->toBe(1600000)
        ->and($openCheckout->refresh()->status)->toBe(DuePayment::StatusAbandoned);

    $this->actingAs($staffUser)
        ->get(route('dashboard.dues.assignments.waive.create', [$due, $assignment]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('dashboard.staffDueWaiverForm.due.id', $due->id)
            ->where('dashboard.staffDueWaiverForm.assignment.id', $assignment->id)
            ->etc());

    $this->actingAs($staffUser)
        ->patch(route('dashboard.dues.assignments.waive', [$due, $assignment]), [
            'reason' => 'Department scholarship',
        ])
        ->assertRedirect(route('dashboard.dues.show', $due));

    expect($assignment->refresh()->status)->toBe(StudentDueAssignment::StatusWaived)
        ->and($assignment->waiver_reason)->toBe('Department scholarship')
        ->and($assignment->waived_by_user_id)->toBe($staffUser->id);

    $this->actingAs($staffUser)
        ->patch(route('dashboard.dues.assignments.restore', [$due, $assignment]))
        ->assertRedirect(route('dashboard.dues.show', $due));

    $this->actingAs($staffUser)
        ->patch(route('dashboard.dues.archive', $due))
        ->assertRedirect(route('dashboard.dues.show', $due));

    expect($due->refresh()->status)->toBe(Due::StatusArchived);

    $this->actingAs($staffUser)
        ->patch(route('dashboard.dues.restore', $due))
        ->assertRedirect(route('dashboard.dues.show', $due));

    expect($due->refresh()->status)->toBe(Due::StatusPublished)
        ->and($assignment->refresh()->status)->toBe(StudentDueAssignment::StatusPending);

    $export = $this->actingAs($staffUser)->get(route('dashboard.dues.export', $due));

    $export->assertOk()->assertDownload('due-payments-departmental-dues-300-level.csv');
    expect($export->streamedContent())
        ->toContain('Registration Number')
        ->toContain($studentProfile->registration_number);

    $this->actingAs($staffUser)
        ->post(route('dashboard.students.store'), [
            'entry_academic_session_id' => $entrySession->id,
            'registration_number' => '2023007777',
            'access_code' => 'CPE-STUDENT-LATE',
        ])
        ->assertRedirect(route('dashboard.section', ['section' => 'students']));

    $lateStudent = StudentProfile::query()->where('registration_number', '2023007777')->firstOrFail();

    expect($due->assignments()->where('student_profile_id', $lateStudent->id)->exists())->toBeTrue();
});

test('Paystack refund webhooks reverse successful payments and reopen the due', function () {
    $this->seed(AcademicStructureSeeder::class);
    Queue::fake();

    Config::set('services.paystack.secret_key', 'sk_test_grade_manager');
    $department = Department::factory()->create();
    $department->load('faculty.school');
    $school = $department->faculty->school;
    $entrySession = AcademicSession::factory()->create([
        'school_id' => $school->id,
        'name' => '2023/2024',
        'starts_on' => '2023-09-01',
        'ends_on' => '2024-08-31',
    ]);
    $currentSession = AcademicSession::factory()->create([
        'school_id' => $school->id,
        'name' => '2025/2026',
        'starts_on' => '2025-09-01',
        'ends_on' => '2026-08-31',
    ]);
    $currentLevel = AcademicYear::query()->where('slug', '300-level')->firstOrFail();
    $studentProfile = StudentProfile::factory()->create([
        'school_id' => $school->id,
        'department_id' => $department->id,
        'entry_academic_session_id' => $entrySession->id,
        'current_academic_year_id' => $currentLevel->id,
    ]);
    $staffProfile = StaffProfile::factory()->create(['department_id' => $department->id]);
    $paymentAccount = createTestPaymentAccount($department, $staffProfile, $entrySession);
    $due = createTestDue($department, $entrySession, $currentSession, 'Departmental Dues', 1500000, $staffProfile, $paymentAccount);
    $assignment = assignTestDue($due, $studentProfile);
    $payment = markTestDuePaid($assignment);
    $rawPayload = json_encode([
        'event' => 'refund.processed',
        'data' => [
            'transaction' => ['reference' => $payment->reference],
        ],
    ], JSON_THROW_ON_ERROR);
    $signature = hash_hmac('sha512', $rawPayload, 'sk_test_grade_manager');

    $this->call('POST', route('paystack.webhook'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_PAYSTACK_SIGNATURE' => $signature,
    ], $rawPayload)->assertNoContent();

    expect($payment->refresh()->status)->toBe(DuePayment::StatusReversed)
        ->and($payment->reversal_reason)->toBe('Paystack refund processed')
        ->and($payment->receipt_status)->toBe(DuePayment::ReceiptStatusPending)
        ->and($assignment->refresh()->status)->toBe(StudentDueAssignment::StatusPending)
        ->and($assignment->latest_due_payment_id)->toBeNull();

    Queue::assertPushed(GenerateDuePaymentReceipt::class);
});

function createTestDue(
    Department $department,
    AcademicSession $entrySession,
    AcademicSession $academicSession,
    string $title,
    int $amountKobo,
    ?StaffProfile $createdByStaffProfile = null,
    ?PaymentAccount $paymentAccount = null,
    string $status = Due::StatusPublished,
): Due {
    $department->loadMissing('faculty');

    return Due::query()->create([
        'school_id' => $department->faculty->school_id,
        'department_id' => $department->id,
        'entry_academic_session_id' => $entrySession->id,
        'academic_session_id' => $academicSession->id,
        'created_by_staff_profile_id' => $createdByStaffProfile?->id,
        'payment_account_id' => $paymentAccount?->id,
        'title' => $title,
        'description' => 'Department levy.',
        'amount_kobo' => $amountKobo,
        'currency' => 'NGN',
        'due_on' => '2026-06-30',
        'status' => $status,
        'published_at' => $status === Due::StatusPublished ? now() : null,
    ]);
}

function createTestPaymentAccount(
    Department $department,
    StaffProfile $staffProfile,
    ?AcademicSession $entrySession = null,
): PaymentAccount {
    $department->loadMissing('faculty');
    $accountNumber = '0123456789';

    return PaymentAccount::query()->create([
        'school_id' => $department->faculty->school_id,
        'department_id' => $department->id,
        'staff_profile_id' => $staffProfile->id,
        'entry_academic_session_id' => $entrySession?->id,
        'provider' => PaymentAccount::ProviderPaystack,
        'label' => 'Test payout account',
        'business_name' => 'CPE dues',
        'bank_code' => '058',
        'bank_name' => 'Guaranty Trust Bank',
        'account_number' => $accountNumber,
        'account_number_last4' => substr($accountNumber, -4),
        'account_name' => $staffProfile->user->name,
        'paystack_subaccount_code' => 'ACCT_TEST_'.$staffProfile->id.'_'.($entrySession?->id ?? 'all'),
        'status' => PaymentAccount::StatusActive,
        'verified_at' => now(),
        'activated_at' => now(),
        'provider_payload' => ['test' => true],
    ]);
}

function assignTestDue(Due $due, StudentProfile $studentProfile): StudentDueAssignment
{
    return StudentDueAssignment::query()->create([
        'due_id' => $due->id,
        'student_profile_id' => $studentProfile->id,
        'amount_kobo' => $due->amount_kobo,
        'status' => StudentDueAssignment::StatusPending,
    ]);
}

function markTestDuePaid(StudentDueAssignment $assignment, string $paidAt = '2026-05-04 10:00:00'): DuePayment
{
    $assignment->loadMissing('due');
    $studentProfile = $assignment->studentProfile;
    $payment = DuePayment::query()->create([
        'student_due_assignment_id' => $assignment->id,
        'student_profile_id' => $studentProfile->id,
        'school_id' => $studentProfile->school_id,
        'payment_account_id' => $assignment->due?->payment_account_id,
        'provider' => DuePayment::ProviderPaystack,
        'reference' => 'DUE-TEST-'.$assignment->id,
        'status' => DuePayment::StatusSuccess,
        'amount_kobo' => $assignment->amount_kobo,
        'currency' => 'NGN',
        'authorization_url' => 'https://checkout.paystack.com/test-'.$assignment->id,
        'access_code' => 'ACCS_test_'.$assignment->id,
        'paystack_transaction_id' => (string) (900000 + $assignment->id),
        'channel' => 'card',
        'gateway_response' => 'Successful',
        'paid_at' => $paidAt,
        'receipt_number' => 'GM-TEST-'.str_pad((string) $assignment->id, 6, '0', STR_PAD_LEFT),
        'receipt_status' => DuePayment::ReceiptStatusReady,
        'receipt_disk' => 'local',
        'receipt_path' => 'receipts/test/'.$assignment->id.'.pdf',
        'receipt_generated_at' => $paidAt,
        'provider_payload' => [
            'status' => DuePayment::StatusSuccess,
            'amount' => $assignment->amount_kobo,
            'currency' => 'NGN',
        ],
    ]);

    $assignment->forceFill([
        'status' => StudentDueAssignment::StatusPaid,
        'paid_at' => $paidAt,
        'latest_due_payment_id' => $payment->id,
    ])->save();

    return $payment;
}

function createApprovedCourseResult(
    Department $department,
    StudentProfile $studentProfile,
    AcademicSession $academicSession,
    AcademicYearSemester $academicYearSemester,
    CourseOffering $courseOffering,
    string $grade,
): StudentCourseResult {
    $resultSheet = ResultSheet::query()->create([
        'school_id' => $department->faculty()->value('school_id'),
        'department_id' => $department->id,
        'entry_academic_session_id' => $studentProfile->entry_academic_session_id,
        'academic_session_id' => $academicSession->id,
        'academic_year_semester_id' => $academicYearSemester->id,
        'status' => ResultSheet::StatusApproved,
        'approved_at' => now(),
    ]);
    $courseOffering->loadMissing('course');
    $sheetCourse = ResultSheetCourse::query()->create([
        'result_sheet_id' => $resultSheet->id,
        'course_offering_id' => $courseOffering->id,
        'course_code' => $courseOffering->course->code,
        'course_title' => $courseOffering->course->title,
        'credit_units' => (int) $courseOffering->course->credit_units,
        'sort_order' => 1,
    ]);
    $gradePoint = app(GpaCalculator::class)->gradePoint($grade);
    $studentResult = StudentSemesterResult::query()->create([
        'result_sheet_id' => $resultSheet->id,
        'student_profile_id' => $studentProfile->id,
        'total_units' => (int) $courseOffering->course->credit_units,
        'total_grade_points' => (int) $courseOffering->course->credit_units * $gradePoint,
        'gpa' => $gradePoint,
    ]);

    return StudentCourseResult::query()->create([
        'student_semester_result_id' => $studentResult->id,
        'result_sheet_course_id' => $sheetCourse->id,
        'grade' => $grade,
        'grade_point' => $gradePoint,
        'quality_points' => (int) $courseOffering->course->credit_units * $gradePoint,
    ]);
}
