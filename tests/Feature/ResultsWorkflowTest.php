<?php

use App\Models\AcademicSessionSemester;
use App\Models\AdvisorProfile;
use App\Models\CourseRegistration;
use App\Models\CourseRegistrationItem;
use App\Models\StudentDueAssignment;
use App\Models\StudentSet;
use App\Models\User;
use App\Services\SetResultsExport;
use App\Services\SetResultsSpreadsheet;
use App\Services\StudentResultStatement;
use Database\Seeders\AcademicStructureSeeder;
use Database\Seeders\DemoUserSeeder;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\Calculation\Calculation;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

test('subjects release separately while incomplete semesters keep GPA pending and corrections are recorded', function () {
    $this->seed([AcademicStructureSeeder::class, DemoUserSeeder::class]);
    $adviser = User::query()->where('email', 'staff1@futo.edu.ng')->firstOrFail();
    $student = User::query()->where('email', 'student3@futo.edu.ng')->firstOrFail();
    StudentDueAssignment::query()->where('student_profile_id', $student->studentProfile->id)
        ->update(['status' => StudentDueAssignment::StatusWaived, 'waived_at' => now()]);
    $set = StudentSet::query()->firstOrFail();
    $period = AcademicSessionSemester::query()
        ->whereHas('academicSession', fn ($query) => $query->where('start_year', 2026))
        ->whereHas('semester', fn ($query) => $query->where('slug', 'first'))
        ->firstOrFail();
    $parameters = [...$set->routeParameters(), 'sessionYears' => '20262027', 'semester' => 'first'];

    $this->actingAs($student)->get(route('dashboard.section', ['section' => 'results']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('dashboard.studentResults.periods', 4));
    $this->get(route('dashboard.student-results.pdf', ['sessionYears' => '20262027', 'semester' => 'first']))->assertNotFound();

    $offerings = $period->courseOfferings()->with('course')->orderBy('id')->get();
    $first = $offerings->firstOrFail();
    $firstRoute = [...$parameters, 'courseCode' => $first->course->urlCode()];

    $this->actingAs($adviser)->post(route('student-sets.results.publish', $firstRoute))
        ->assertSessionHasErrors('results');

    foreach ($offerings as $offering) {
        $items = CourseRegistrationItem::query()->where('course_offering_id', $offering->id)
            ->whereHas('courseRegistration', fn ($query) => $query->where('status', CourseRegistration::StatusAssigned))
            ->get();
        $courseRoute = [...$parameters, 'courseCode' => $offering->course->urlCode()];
        $this->patch(route('student-sets.results.course.save', $courseRoute), [
            'grades' => $items->mapWithKeys(fn ($item) => [(string) $item->id => 'A'])->all(),
        ])->assertSessionHasNoErrors();
    }

    $this->post(route('student-sets.results.publish', $firstRoute))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('student-sets.results.course', $firstRoute));
    expect($first->refresh()->results_published_at)->not->toBeNull();

    $this->actingAs($student)->get(route('dashboard.section', ['section' => 'results']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('dashboard.studentResults.periods', 5)
        ->has('dashboard.studentResults.history', 5)
        ->where('dashboard.studentResults.selected', null)
        ->where('dashboard.studentResults.history.4.gpa', null));
    $this->get(route('dashboard.section', [
        'section' => 'results', 'session' => '20262027', 'semester' => 'first',
    ]))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('dashboard.studentResults.selected.gpa', null)
        ->where('dashboard.studentResults.selected.courses', fn ($courses) => collect($courses)->contains(fn ($course) => $course['code'] === $first->course->code && $course['grade'] === 'A')
            && collect($courses)->contains(fn ($course) => $course['pending'] === true)));
    $this->get(route('dashboard.student-results.pdf', ['sessionYears' => '20262027', 'semester' => 'first']))
        ->assertOk()->assertDownload('chiamaka-eze-2024000003-20262027-first-results.pdf');

    foreach ($offerings->skip(1) as $offering) {
        $this->actingAs($adviser)->post(route('student-sets.results.publish', [
            ...$parameters, 'courseCode' => $offering->course->urlCode(),
        ]))->assertSessionHasNoErrors();
    }

    $this->get(route('student-sets.matrix.show', [
        ...$set->routeParameters(), 'session' => '20262027', 'semester' => 'first',
    ]))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('dashboard.adviserSetResults.students', fn ($students) => collect($students)->contains(
            fn (array $row): bool => $row['registrationNumber'] === $student->studentProfile->registration_number
                && $row['totalGradePoints'] === 105
                && $row['totalNumberOfUnits'] === 21,
        )));

    $this->get(route('student-sets.matrix.show', [
        ...$set->routeParameters(), 'all' => 1, 'session' => '20242025',
    ]))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('dashboard.adviserSetResults.allHistory', true)
        ->where('dashboard.adviserSetResults.selectedSessionPublicId', '20242025')
        ->where('dashboard.adviserSetResults.columns', fn ($columns) => collect($columns)->contains(
            fn (array $column): bool => str_contains($column['group'], '1st Semester'),
        ) && collect($columns)->contains(
            fn (array $column): bool => str_contains($column['group'], '2nd Semester'),
        ) && collect($columns)->every(
            fn (array $column): bool => $column['session'] === '2024/2025',
        )));
    $this->get(route('student-sets.matrix.show', [
        ...$set->routeParameters(), 'all' => 1, 'session' => '20282029',
    ]))->assertNotFound();

    $this->actingAs($student)->get(route('dashboard.section', ['section' => 'results']))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('dashboard.studentResults.history.4.gpa', 5));

    $item = CourseRegistrationItem::query()->where('course_offering_id', $first->id)
        ->whereHas('courseRegistration', fn ($query) => $query->where('status', CourseRegistration::StatusAssigned))
        ->firstOrFail();
    $correctedGrades = CourseRegistrationItem::query()->where('course_offering_id', $first->id)
        ->whereHas('courseRegistration', fn ($query) => $query->where('status', CourseRegistration::StatusAssigned))
        ->get()->mapWithKeys(fn ($row) => [(string) $row->id => $row->id === $item->id ? 'B' : 'A'])->all();
    $this->actingAs($adviser)->patch(route('student-sets.results.course.save', [
        ...$parameters, 'courseCode' => $first->course->urlCode(),
    ]), ['grades' => $correctedGrades])->assertSessionHasErrors('reason');
    $this->actingAs($adviser)->patch(route('student-sets.results.course.save', [
        ...$parameters, 'courseCode' => $first->course->urlCode(),
    ]), ['grades' => $correctedGrades, 'reason' => 'Verified the score sheet.'])->assertSessionHasNoErrors();
    expect(DB::table('grade_corrections')->where('course_registration_item_id', $item->id)->exists())->toBeTrue();

    $otherAdviser = AdvisorProfile::factory()->create();
    $this->actingAs($otherAdviser->user)->get(route('student-sets.matrix.show', $set->routeParameters()))->assertNotFound();
    $this->get(route('student-sets.results.course', [
        ...$parameters, 'courseCode' => $first->course->urlCode(),
    ]))->assertNotFound();
});

test('set result exports group editable semesters into separate sheets and stay adviser scoped', function () {
    $this->seed([AcademicStructureSeeder::class, DemoUserSeeder::class]);
    $adviser = User::query()->where('email', 'staff1@futo.edu.ng')->firstOrFail();
    $set = StudentSet::query()->firstOrFail();
    $report = app(SetResultsExport::class)->build($set, null, null, true);
    $book = app(SetResultsSpreadsheet::class)->build($report);
    $sheet = $book->getActiveSheet();

    expect(count($report['groups']))->toBeGreaterThan(1)
        ->and($book->getSheetCount())->toBe(count($report['groups']))
        ->and($book->getDefaultStyle()->getFont()->getName())->toBe('Tahoma')
        ->and($sheet->getFreezePane())->toBe('D8')
        ->and($sheet->getCell('D4')->getValue())->toBe('Courses and Units')
        ->and($sheet->getCell(Coordinate::stringFromColumnIndex(4 + count($report['groups'][0]['columns'])).'4')->getValue())->toBe('Current')
        ->and($book->getSheet(1)->getCell(Coordinate::stringFromColumnIndex(7 + count($report['groups'][1]['columns'])).'4')->getValue())->toBe('Previous');

    $group = $report['groups'][0];
    $studentIndex = collect($report['students'])->search(fn (array $student): bool => collect($student['periods'][$group['id']]['cells'])
        ->contains(fn (string $grade): bool => in_array($grade, ['A', 'B', 'C', 'D', 'E', 'F'], true)));
    expect($studentIndex)->not->toBeFalse();
    $courseIndex = array_search('A', $report['students'][$studentIndex]['periods'][$group['id']]['cells'], true);
    if ($courseIndex === false) {
        $courseIndex = array_search('B', $report['students'][$studentIndex]['periods'][$group['id']]['cells'], true);
    }
    expect($courseIndex)->not->toBeFalse();
    $row = $studentIndex + 8;
    $gradeCell = Coordinate::stringFromColumnIndex(4 + $courseIndex).$row;
    $summaryCell = Coordinate::stringFromColumnIndex(4 + count($group['columns'])).$row;
    $cumulativePointsCell = Coordinate::stringFromColumnIndex(10 + count($group['columns'])).$row;
    $before = $sheet->getCell($summaryCell)->getCalculatedValue();
    expect($sheet->getCell($cumulativePointsCell)->getCalculatedValue())->toBe($before);
    $oldGrade = (string) $sheet->getCell($gradeCell)->getValue();
    $newGrade = str_starts_with($oldGrade, 'A') ? 'B' : 'A';
    $sheet->setCellValue($gradeCell, $newGrade.(str_ends_with($oldGrade, ' CO') ? ' CO' : ''));
    Calculation::getInstance($book)->clearCalculationCache();
    expect($sheet->getCell($summaryCell)->getCalculatedValue())->not->toBe($before);
    $previousPoints = Coordinate::stringFromColumnIndex(7 + count($report['groups'][1]['columns'])).$row;
    expect($book->getSheet(1)->getCell($previousPoints)->getValue())->toContain('MATCH($B'.$row);
    expect($book->getSheet(1)->getCell($previousPoints)->getCalculatedValue())->toBe(
        $sheet->getCell($cumulativePointsCell)->getCalculatedValue(),
    );
    if (getenv('ACMS_ARTIFACT_PREVIEWS') === '1') {
        (new Xlsx($book))
            ->save(storage_path('app/private/design-previews/set-results.xlsx'));
    }
    $book->disconnectWorksheets();

    $firstSemester = app(SetResultsExport::class)->build($set, '20242025', 'first', false);
    expect(collect($firstSemester['students'])->every(fn (array $student): bool => $student['starting']['points'] === 0
        && $student['starting']['units'] === 0))->toBeTrue();

    $url = route('student-sets.matrix.export', [...$set->routeParameters(), 'all' => 1]);
    $this->actingAs($adviser)->get($url)->assertOk()->assertDownload();
    $otherAdviser = AdvisorProfile::factory()->create();
    $this->actingAs($otherAdviser->user)->get($url)->assertNotFound();
});

test('student record shows a published GPA matrix and scoped semester detail', function () {
    $this->seed([AcademicStructureSeeder::class, DemoUserSeeder::class]);
    $adviser = User::query()->where('email', 'staff1@futo.edu.ng')->firstOrFail();
    $student = User::query()->where('email', 'student3@futo.edu.ng')->firstOrFail()->studentProfile;
    $profileUrl = route('dashboard.students.show', ['registrationNumber' => $student->registration_number]);
    $resultUrl = route('dashboard.students.results.semester', [
        'registrationNumber' => $student->registration_number,
        'sessionYears' => '20242025',
        'semester' => 'first',
    ]);

    $this->actingAs($adviser)->get($profileUrl)->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('dashboard.studentRecord.results.columns.0.level', '100 Level')
        ->where('dashboard.studentRecord.results.columns.0.semesters.0.url', $resultUrl)
        ->where('dashboard.studentRecord.results.columns.2.semesters.0.gpa', null)
        ->has('dashboard.studentRecord.dues.unpaid')
        ->has('dashboard.studentRecord.dues.paid'));
    $this->get($resultUrl)->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('dashboard.studentRecordResults.student.registrationNumber', $student->registration_number)
        ->has('dashboard.studentRecordResults.results.selected.courses'));

    $previewUrl = route('dashboard.students.statement.preview', ['registrationNumber' => $student->registration_number]);
    $downloadUrl = route('dashboard.students.statement.download', ['registrationNumber' => $student->registration_number]);
    $this->get($previewUrl)->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('dashboard.studentRecordStatement.student.registrationNumber', $student->registration_number)
        ->has('dashboard.studentRecordStatement.periods'));
    $statement = app(StudentResultStatement::class)->build($student);
    foreach ($statement['periods'] as $period) {
        expect($period['totalUnits'])->toBe(array_sum(array_column($period['courses'], 'units')));
    }
    $download = $this->get($downloadUrl)->assertOk()->assertDownload('results-chiamaka-eze-'.$student->registration_number.'-'.now()->format('Ymd').'.docx');
    $path = $download->baseResponse->getFile()->getPathname();
    $word = new ZipArchive;
    expect($word->open($path))->toBeTrue();
    expect($word->getFromName('word/document.xml'))
        ->toContain('Statement of Results for '.$student->user->name, 'Total Units', 'REGISTRATION NUMBER')
        ->not->toContain('Published units', '<w:shd', '<w:drawing', 'ACMS');
    $word->close();
    unlink($path);

    $this->get(route('dashboard.students.results.semester', [
        'registrationNumber' => $student->registration_number,
        'sessionYears' => '20262027',
        'semester' => 'first',
    ]))->assertNotFound();

    $otherAdviser = AdvisorProfile::factory()->create();
    $this->actingAs($otherAdviser->user)->get($profileUrl)->assertNotFound();
    $this->get($resultUrl)->assertNotFound();
    $this->get($previewUrl)->assertNotFound();
    $this->get($downloadUrl)->assertNotFound();
});

test('a published missing script is visible without a GPA and its correction is audited', function () {
    $this->seed([AcademicStructureSeeder::class, DemoUserSeeder::class]);
    $adviser = User::query()->where('email', 'staff1@futo.edu.ng')->firstOrFail();
    $set = StudentSet::query()->firstOrFail();
    $period = AcademicSessionSemester::query()
        ->whereHas('academicSession', fn ($query) => $query->where('start_year', 2026))
        ->whereHas('semester', fn ($query) => $query->where('slug', 'first'))
        ->firstOrFail();
    $offering = $period->courseOfferings()->with('course')->orderBy('id')->firstOrFail();
    $items = CourseRegistrationItem::query()->where('course_offering_id', $offering->id)
        ->whereHas('courseRegistration', fn ($query) => $query->where('status', CourseRegistration::StatusAssigned))
        ->get();
    $missingItem = $items->firstOrFail();
    StudentDueAssignment::query()->where('student_profile_id', $missingItem->courseRegistration->student_profile_id)
        ->update(['status' => StudentDueAssignment::StatusWaived, 'waived_at' => now()]);
    $route = [
        ...$set->routeParameters(),
        'sessionYears' => '20262027',
        'semester' => 'first',
        'courseCode' => $offering->course->urlCode(),
    ];
    $grades = $items->mapWithKeys(fn ($item) => [(string) $item->id => $item->id === $missingItem->id ? 'missing_script' : 'A'])->all();

    $this->actingAs($adviser)->patch(route('student-sets.results.course.save', $route), [
        'grades' => $grades,
    ])->assertSessionHasNoErrors();

    expect($missingItem->refresh()->grade)->toBeNull()
        ->and($missingItem->missing_script)->toBeTrue();

    $this->post(route('student-sets.results.publish', $route))->assertSessionHasNoErrors();

    $student = $missingItem->courseRegistration->studentProfile->user;
    $courseIndex = $missingItem->courseRegistration->items()->orderBy('course_code')->pluck('course_code')
        ->search($missingItem->course_code);
    $studentResults = route('dashboard.section', [
        'section' => 'results', 'session' => '20262027', 'semester' => 'first',
    ]);

    $this->actingAs($student)->get($studentResults)->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('dashboard.studentResults.selected.gpa', null)
        ->where("dashboard.studentResults.selected.courses.{$courseIndex}.missingScript", true)
        ->where("dashboard.studentResults.selected.courses.{$courseIndex}.grade", null));

    $this->actingAs($adviser)->patch(route('student-sets.results.course.save', $route), [
        'grades' => array_replace($grades, [(string) $missingItem->id => 'A']),
    ])->assertSessionHasErrors('reason');
    $this->patch(route('student-sets.results.course.save', $route), [
        'grades' => array_replace($grades, [(string) $missingItem->id => 'A']),
        'reason' => 'The marked script was found and graded.',
    ])->assertSessionHasNoErrors();

    expect($missingItem->refresh()->grade)->toBe('A')
        ->and($missingItem->missing_script)->toBeFalse();
    expect(DB::table('grade_corrections')->where('course_registration_item_id', $missingItem->id)
        ->where('old_missing_script', true)->where('new_grade', 'A')->exists())->toBeTrue();
});
