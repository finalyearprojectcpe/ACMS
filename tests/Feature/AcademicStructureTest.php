<?php

use App\AccountType;
use App\Models\AcademicYear;
use App\Models\AcademicYearSemester;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\School;
use App\Models\Semester;
use App\Models\StaffProfile;
use App\Models\StudentProfile;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

test('database seeder creates predictable cpe academic records', function () {
    $this->seed(DatabaseSeeder::class);

    $faculty = Faculty::query()->where('abbreviation', 'SESET')->firstOrFail();
    $department = Department::query()->where('abbreviation', 'CPE')->firstOrFail();
    $school = School::query()->where('abbreviation', 'FUTO')->firstOrFail();
    $schoolAdmin = User::query()->where('email', 'schooladmin@futo.edu')->firstOrFail();
    $hod = User::query()->where('email', 'staff1@futo.edu')->firstOrFail();
    $facultyHead = User::query()->where('email', 'facultyhead.seset@futo.edu')->firstOrFail();

    expect(AcademicYear::query()->count())->toBe(7)
        ->and($faculty->school_id)->toBe($school->id)
        ->and($schoolAdmin->account_type)->toBe(AccountType::SchoolAdmin)
        ->and(AcademicYearSemester::query()->count())->toBe(14)
        ->and(Semester::query()->pluck('slug')->all())->toBe(['harmattan', 'rain'])
        ->and($department->minimum_years_to_degree)->toBe(5)
        ->and($faculty->head_staff_profile_id)->toBe($facultyHead->staffProfile->id)
        ->and($department->hod_staff_profile_id)->toBe($hod->staffProfile->id)
        ->and($department->studentSetAdvisorAssignments()->where('active_marker', 1)->count())->toBe(5)
        ->and(User::query()->where('email', 'like', 'student%@futo.edu')->count())->toBe(6)
        ->and(Course::query()->count())->toBe(30)
        ->and(CourseOffering::query()->count())->toBe(270)
        ->and($school->academicSessions()->count())->toBe(9)
        ->and($school->academicSessions()->where('name', '2025/2026')->whereDate('starts_on', '<=', today())->whereDate('ends_on', '>=', today())->exists())->toBeTrue()
        ->and(Course::query()->where('code', 'CPE 301')->firstOrFail()->prerequisites()->where('code', 'CPE 202')->exists())->toBeTrue();

    $studentLevels = StudentProfile::query()
        ->with(['currentLevel', 'user'])
        ->whereHas('user', fn ($query) => $query->whereIn('email', [
            'student1@futo.edu',
            'student2@futo.edu',
            'student3@futo.edu',
            'student4@futo.edu',
            'student5@futo.edu',
        ]))
        ->get()
        ->mapWithKeys(fn (StudentProfile $profile): array => [
            $profile->user->email => $profile->currentLevel?->slug,
        ])
        ->sortKeys()
        ->all();

    expect($studentLevels)->toBe([
        'student1@futo.edu' => '100-level',
        'student2@futo.edu' => '200-level',
        'student3@futo.edu' => '300-level',
        'student4@futo.edu' => '400-level',
        'student5@futo.edu' => '500-level',
    ]);
});

test('faculty head transfer is requested and accepted by staff in the faculty', function () {
    $faculty = Faculty::factory()->create();
    $department = Department::factory()->create([
        'faculty_id' => $faculty->id,
    ]);
    $currentHead = StaffProfile::factory()->create([
        'department_id' => $department->id,
    ]);
    $nextHead = StaffProfile::factory()->create([
        'department_id' => $department->id,
    ]);

    $faculty->assignHead($currentHead);
    $faculty->requestHeadTransfer($nextHead);

    expect($faculty->head_staff_profile_id)->toBe($currentHead->id)
        ->and($faculty->pending_head_staff_profile_id)->toBe($nextHead->id);

    $faculty->acceptHeadTransfer($nextHead);

    expect($faculty->head_staff_profile_id)->toBe($nextHead->id)
        ->and($faculty->pending_head_staff_profile_id)->toBeNull();
});

test('department visible academic years include minimum years and years with records', function () {
    $this->seed(DatabaseSeeder::class);

    $department = Department::query()->where('abbreviation', 'CPE')->firstOrFail();

    expect($department->visibleAcademicYears()->pluck('slug')->all())->toBe([
        '100-level',
        '200-level',
        '300-level',
        '400-level',
        '500-level',
    ]);

    $department->setMinimumYearsToDegree(3);

    expect($department->visibleAcademicYears()->pluck('slug')->all())->toBe([
        '100-level',
        '200-level',
        '300-level',
        '400-level',
        '500-level',
    ]);
});

test('course prerequisites belong to reusable course records', function () {
    $course = Course::factory()->create([
        'code' => 'CPE 402',
        'title' => 'Advanced Embedded Systems',
        'slug' => 'cpe-402-advanced-embedded-systems',
    ]);
    $prerequisite = Course::factory()->create([
        'code' => 'CPE 301',
        'title' => 'Microprocessor Systems',
        'slug' => 'cpe-301-microprocessor-systems',
    ]);

    $course->addPrerequisite($prerequisite);
    $course->addPrerequisite($prerequisite);

    expect($course->prerequisites)->toHaveCount(1)
        ->and($course->prerequisites->first()->is($prerequisite))->toBeTrue();
});
