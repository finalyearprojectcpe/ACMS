<?php

use App\Models\AcademicSessionSemester;
use App\Models\AdvisorProfile;
use App\Models\AssistantConversation;
use App\Models\Due;
use App\Models\DuePayment;
use App\Models\StudentDueAssignment;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\DueAssignmentService;
use App\Services\StudentAssistantRecordTool;
use App\Services\StudentResultAccess;
use Database\Seeders\AcademicStructureSeeder;
use Database\Seeders\DemoUserSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Ai\Tools\Request as ToolRequest;

test('published period dues hide only applicable student results until settled or archived', function () {
    $this->seed([AcademicStructureSeeder::class, DemoUserSeeder::class]);
    $student = User::query()->where('email', 'student3@futo.edu.ng')->firstOrFail();
    $profile = $student->studentProfile;
    $first = AcademicSessionSemester::query()
        ->whereHas('academicSession', fn ($query) => $query->where('start_year', 2024))
        ->whereHas('semester', fn ($query) => $query->where('slug', 'first'))
        ->firstOrFail();
    $second = AcademicSessionSemester::query()
        ->where('academic_session_id', $first->academic_session_id)
        ->whereHas('semester', fn ($query) => $query->where('slug', 'second'))
        ->firstOrFail();
    $due = Due::query()->where('student_set_id', $profile->student_set_id)
        ->where('academic_session_semester_id', $first->id)->firstOrFail();
    $firstUrl = route('dashboard.section', ['section' => 'results', 'session' => '20242025', 'semester' => 'first']);
    $secondUrl = route('dashboard.section', ['section' => 'results', 'session' => '20242025', 'semester' => 'second']);
    $coursesPdf = route('dashboard.student-courses.pdf', ['sessionYears' => '20242025', 'semester' => 'first']);
    $resultsPdf = route('dashboard.student-results.pdf', ['sessionYears' => '20242025', 'semester' => 'first']);

    $this->get($coursesPdf)->assertRedirect();
    $courseDownload = $this->actingAs($student)->get($coursesPdf)->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertDownload('chiamaka-eze-2024000003-20242025-first-courses.pdf');
    if (getenv('ACMS_ARTIFACT_PREVIEWS') === '1') {
        file_put_contents(storage_path('app/private/design-previews/student-semester-courses.pdf'), $courseDownload->getContent());
    }
    $this->get($resultsPdf)->assertForbidden();
    $this->actingAs($due->advisorProfile->user)->get($coursesPdf)->assertForbidden();
    $this->actingAs($student)->get(route('dashboard.student-courses.pdf', ['sessionYears' => '20302031', 'semester' => 'first']))->assertNotFound();

    $this->actingAs($student)->get($firstUrl)->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('dashboard.studentResults.selected.locked', true)
        ->where('dashboard.studentResults.selected.gpa', null)
        ->has('dashboard.studentResults.selected.courses', 0));
    $this->get($secondUrl)->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('dashboard.studentResults.selected.locked', false)
        ->has('dashboard.studentResults.selected.courses'));
    $this->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('dashboard.studentHome.resultsLocked', true)
        ->where('dashboard.studentHome.cgpa', null));
    expect(app(StudentResultAccess::class)->accessibleSemesterIds($profile))
        ->not->toContain($first->id)
        ->toContain($second->id);

    $assignment = StudentDueAssignment::query()
        ->where('due_id', $due->id)->where('student_profile_id', $profile->id)->firstOrFail();
    app(DueAssignmentService::class)->waive($assignment, $due->advisorProfile->user, 'Adviser approved');
    $this->get($firstUrl)->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('dashboard.studentResults.selected.locked', false)
        ->has('dashboard.studentResults.selected.courses'));
    $download = $this->get($resultsPdf)->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertDownload('chiamaka-eze-2024000003-20242025-first-results.pdf');
    expect(str_starts_with($download->getContent(), '%PDF-'))->toBeTrue();
    if (getenv('ACMS_ARTIFACT_PREVIEWS') === '1') {
        file_put_contents(storage_path('app/private/design-previews/student-semester-results.pdf'), $download->getContent());
    }

    $sessionDue = Due::query()->create([
        'advisor_profile_id' => $due->advisor_profile_id,
        'student_set_id' => $due->student_set_id,
        'academic_session_id' => $due->academic_session_id,
        'academic_session_semester_id' => null,
        'payment_account_id' => $due->payment_account_id,
        'title' => 'Whole session levy',
        'amount_kobo' => 100000,
        'currency' => 'NGN',
        'status' => Due::StatusPublished,
        'published_at' => now(),
    ]);
    app(DueAssignmentService::class)->syncForDue($sessionDue);
    $this->get($secondUrl)->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('dashboard.studentResults.selected.locked', true));
    app(DueAssignmentService::class)->archive($sessionDue);
    $this->get($secondUrl)->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('dashboard.studentResults.selected.locked', false));
});

test('due list exports a plain Word document for the selected payment status', function () {
    $this->seed([AcademicStructureSeeder::class, DemoUserSeeder::class]);
    $adviser = User::query()->where('email', 'staff1@futo.edu.ng')->firstOrFail();
    $due = Due::query()->where('title', 'Orientation Dues 100 Level')->firstOrFail();
    $paidStudent = StudentProfile::query()->where('registration_number', '2024000001')->firstOrFail();
    $waivedStudent = StudentProfile::query()->where('registration_number', '2024000005')->firstOrFail();
    $unclaimedStudent = StudentProfile::query()->where('registration_number', '2024000006')->firstOrFail();
    $paidAssignment = StudentDueAssignment::query()
        ->where('due_id', $due->id)->where('student_profile_id', $paidStudent->id)->firstOrFail();
    $waivedAssignment = StudentDueAssignment::query()
        ->where('due_id', $due->id)->where('student_profile_id', $waivedStudent->id)->firstOrFail();
    expect(StudentDueAssignment::query()->where('due_id', $due->id)
        ->where('student_profile_id', $unclaimedStudent->id)->exists())->toBeTrue();

    $payment = DuePayment::query()->create([
        'student_due_assignment_id' => $paidAssignment->id,
        'student_profile_id' => $paidStudent->id,
        'advisor_profile_id' => $due->advisor_profile_id,
        'payment_account_id' => $due->payment_account_id,
        'provider' => DuePayment::ProviderPaystack,
        'reference' => 'DUE-ROSTER-EXAMPLE',
        'status' => DuePayment::StatusSuccess,
        'amount_kobo' => 123400,
        'currency' => 'NGN',
        'paid_at' => now(),
    ]);
    $paidAssignment->forceFill([
        'status' => StudentDueAssignment::StatusPaid,
        'paid_at' => now(),
        'latest_due_payment_id' => $payment->id,
    ])->save();
    app(DueAssignmentService::class)->waive($waivedAssignment, $adviser, 'Adviser approved');

    $url = route('dashboard.dues.roster.download', ['due' => $due]);
    $this->get($url)->assertRedirect();
    $this->actingAs($paidStudent->user)->get($url)->assertForbidden();
    $otherAdviser = AdvisorProfile::factory()->create();
    $this->actingAs($otherAdviser->user)->get($url)->assertNotFound();

    $download = $this->actingAs($adviser)->get($url)->assertOk()
        ->assertDownload('orientation-dues-100-level-20242025-computer-engineering-20242025-all-'.now()->format('Ymd').'.docx')
        ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    $path = $download->baseResponse->getFile()->getPathname();
    $word = new ZipArchive;
    expect($word->open($path))->toBeTrue();
    $xml = $word->getFromName('word/document.xml');
    expect($xml)->toContain('Orientation Dues 100 Level Payment List', 'Paid', 'Unpaid', 'Waived', 'S/N', 'Kelechi Okeke', '₦1,234.00')
        ->not->toContain('<w:shd', '<w:drawing', 'ACMS');
    $word->close();
    unlink($path);

    foreach (['paid', 'unpaid', 'waived'] as $status) {
        $filtered = $this->get($url.'?status='.$status)->assertOk()
            ->assertDownload('orientation-dues-100-level-20242025-computer-engineering-20242025-'.$status.'-'.now()->format('Ymd').'.docx');
        $filteredPath = $filtered->baseResponse->getFile()->getPathname();
        $word = new ZipArchive;
        expect($word->open($filteredPath))->toBeTrue();
        $filteredXml = $word->getFromName('word/document.xml');
        expect($filteredXml)->toContain(ucfirst($status).' Students for Orientation Dues 100 Level')
            ->not->toContain('<w:shd', '<w:drawing', 'ACMS');
        foreach (array_diff(['paid', 'unpaid', 'waived'], [$status]) as $excludedStatus) {
            expect($filteredXml)->not->toContain('>'.ucfirst($excludedStatus).'</w:t>');
        }
        $word->close();
        unlink($filteredPath);
    }

    $this->get($url.'?status=invalid')->assertSessionHasErrors('status');
});

test('student assistant cannot reopen a chat created before results became locked', function () {
    $this->seed([AcademicStructureSeeder::class, DemoUserSeeder::class]);
    $student = User::query()->where('email', 'student3@futo.edu.ng')->firstOrFail();
    $conversation = AssistantConversation::query()->create([
        'student_profile_id' => $student->studentProfile->id,
        'title' => 'Past results',
    ]);
    $conversation->forceFill(['created_at' => now()->subHour()])->save();

    $this->actingAs($student)->get(route('dashboard.assistant.show', ['assistantConversation' => $conversation]))->assertNotFound();
});

test('student assistant reports locked performance without exposing grades', function () {
    $this->seed([AcademicStructureSeeder::class, DemoUserSeeder::class]);
    $student = User::query()->where('email', 'student3@futo.edu.ng')->firstOrFail()->studentProfile;
    $tool = app()->makeWith(StudentAssistantRecordTool::class, ['student' => $student]);

    $performance = json_decode((string) $tool->handle(new ToolRequest([
        'category' => 'performance',
        'session' => '20242025',
        'semester' => 'first',
    ])), true, flags: JSON_THROW_ON_ERROR);
    $results = json_decode((string) $tool->handle(new ToolRequest([
        'category' => 'results',
        'session' => '20242025',
        'semester' => 'first',
    ])), true, flags: JSON_THROW_ON_ERROR);

    expect($performance['count'])->toBe(1)
        ->and($performance['records'][0]['detail'])->toContain('Results locked')
        ->not->toContain('GPA')
        ->and($results['count'])->toBe(0);

    $firstDues = json_decode((string) $tool->handle(new ToolRequest([
        'category' => 'dues',
        'session' => '20262027',
        'semester' => 'first',
    ])), true, flags: JSON_THROW_ON_ERROR);
    $secondDues = json_decode((string) $tool->handle(new ToolRequest([
        'category' => 'dues',
        'session' => '20262027',
        'semester' => 'second',
    ])), true, flags: JSON_THROW_ON_ERROR);
    $matchingDues = json_decode((string) $tool->handle(new ToolRequest([
        'category' => 'dues',
        'term' => 'student association levy',
    ])), true, flags: JSON_THROW_ON_ERROR);

    expect(collect($firstDues['records'])->pluck('title'))
        ->toContain('Student Association Levy', 'Departmental Dues 300 Level')
        ->and(collect($secondDues['records'])->pluck('title'))
        ->toContain('Student Association Levy')
        ->not->toContain('Departmental Dues 300 Level')
        ->and(collect($matchingDues['records'])->pluck('title'))->toContain('Student Association Levy');
});
