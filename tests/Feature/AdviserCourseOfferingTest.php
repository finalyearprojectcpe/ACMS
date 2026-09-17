<?php

use App\CourseCategory;
use App\Models\AcademicSession;
use App\Models\AcademicYear;
use App\Models\AcademicYearSemester;
use App\Models\AdvisorProfile;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Semester;
use App\Models\StudentSet;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\AcademicStructureSeeder;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

test('course advisers see course offerings up to their student sets current level', function () {
    $this->seed(AcademicStructureSeeder::class);
    $this->travelTo(CarbonImmutable::parse('2026-01-15'));
    $user = createAdvisorCourseScene();

    $response = $this->actingAs($user)->get(route('dashboard.section', [
        'section' => 'courses',
        'level' => '300-level',
    ]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->where('dashboard.role', 'adviser')
        ->where('dashboard.section', 'courses')
        ->where('dashboard.sectionTitle', 'Course Offerings')
        ->has('dashboard.levels', 3)
        ->where('dashboard.levels.0.slug', '100-level')
        ->where('dashboard.courseScheduleLevels.2.slug', '300-level')
        ->has('dashboard.adviserCourseSchedules', fn (Assert $page) => $page
            ->where('level.title', '300 Level')
            ->has('levels', 3)
            ->where('levels.2.slug', '300-level')
            ->where('academicSession.name', '2025/2026')
            ->where('selectedSet.currentLevel.title', '300 Level')
            ->has('assignedSets', 1)
            ->has('semesters', 2)
            ->where('semesters.0.name', 'Harmattan')
            ->where('semesters.0.totalUnits', 10)
            ->where('semesters.0.normalCourseCount', 2)
            ->where('semesters.0.electiveCourseCount', 2)
            ->has('semesters.0.offerings', 4)
            ->where('semesters.0.offerings.0.code', 'CPE 301')
            ->where('semesters.0.offerings.0.prerequisites.0.code', 'CPE 202')
            ->where('semesters.0.offerings.1.category', 'normal')
            ->where('semesters.0.offerings.2.category', 'elective')
        )
        ->etc());
});

test('course advisers can create a course offering with prerequisites', function () {
    $this->seed(AcademicStructureSeeder::class);
    $this->travelTo(CarbonImmutable::parse('2026-01-15'));
    $user = createAdvisorCourseScene();
    $set = StudentSet::query()->where('advisor_profile_id', $user->advisorProfile->id)->firstOrFail();
    $prerequisite = Course::query()->where('code', 'CPE 301')->firstOrFail();
    $response = $this->actingAs($user)->post(route('dashboard.courses.store', [
        'level' => '300-level',
        'semester' => 'rain',
        'set' => $set->id,
    ]), [
        'code' => 'CPE 309',
        'title' => 'Computer Engineering Laboratory',
        'credit_units' => 1,
        'is_elective' => true,
        'prerequisite_course_ids' => [$prerequisite->id],
    ]);

    $response->assertRedirect(route('dashboard.section', [
        'section' => 'courses',
        'set' => $set->id,
        'level' => '300-level',
        'semester' => 'rain',
    ]));

    $course = Course::query()->where('code', 'CPE 309')->firstOrFail();
    $offering = CourseOffering::query()
        ->where('student_set_id', $set->id)
        ->where('course_id', $course->id)
        ->firstOrFail();

    expect($offering->credit_units)->toBe(1)
        ->and($offering->category)->toBe(CourseCategory::Elective)
        ->and($offering->prerequisiteCourses()->where('code', 'CPE 301')->exists())->toBeTrue();
});

/**
 * Creates an adviser with a three-year programme, one entry set, and a populated 300-level harmattan schedule.
 *
 * @return User the adviser user
 */
function createAdvisorCourseScene(): User
{
    $advisor = AdvisorProfile::factory()->create([
        'minimum_years_to_degree' => 3,
    ]);
    $entrySession = AcademicSession::factory()->create([
        'advisor_profile_id' => $advisor->id,
        'name' => '2023/2024',
        'starts_on' => '2023-09-01',
        'ends_on' => '2024-08-31',
    ]);
    $set = StudentSet::factory()->create([
        'advisor_profile_id' => $advisor->id,
        'entry_academic_session_id' => $entrySession->id,
        'minimum_years_to_degree' => 3,
    ]);
    AcademicSession::factory()->create([
        'advisor_profile_id' => $advisor->id,
        'name' => '2024/2025',
        'starts_on' => '2024-09-01',
        'ends_on' => '2025-08-31',
    ]);
    $currentSession = AcademicSession::factory()->create([
        'advisor_profile_id' => $advisor->id,
        'name' => '2025/2026',
        'starts_on' => '2025-09-01',
        'ends_on' => '2026-08-31',
    ]);

    $ce202 = createCourse($advisor, 'CPE 202', 'Digital Systems', 3);
    $ce301 = createCourse($advisor, 'CPE 301', 'Computer Architecture', 3);
    $ce302 = createCourse($advisor, 'CPE 302', 'Computer Communications', 2);
    $mth305 = createCourse($advisor, 'MTH 305', 'Engineering Mathematics III', 3);
    $ce306 = createCourse($advisor, 'CPE 306', 'Microprocessors', 2);

    createOffering($set, $ce202, $currentSession, '100-level', 'harmattan', CourseCategory::Normal);
    $offeringCe301 = createOffering($set, $ce301, $currentSession, '300-level', 'harmattan', CourseCategory::Normal);
    createOffering($set, $ce302, $currentSession, '300-level', 'harmattan', CourseCategory::Normal);
    createOffering($set, $mth305, $currentSession, '300-level', 'harmattan', CourseCategory::Elective);
    createOffering($set, $ce306, $currentSession, '300-level', 'harmattan', CourseCategory::Elective);
    $offeringCe301->prerequisiteCourses()->sync([$ce202->id]);

    return $advisor->user;
}

function createCourse(AdvisorProfile $advisor, string $code, string $title, int $creditUnits): Course
{
    return Course::query()->create([
        'advisor_profile_id' => $advisor->id,
        'code' => $code,
        'title' => $title,
        'slug' => Str::slug("{$code} {$title}"),
        'credit_units' => $creditUnits,
    ]);
}

function createOffering(
    StudentSet $set,
    Course $course,
    AcademicSession $session,
    string $levelSlug,
    string $semesterSlug,
    CourseCategory $category,
): CourseOffering {
    $slot = AcademicYearSemester::query()
        ->where('academic_year_id', AcademicYear::query()->where('slug', $levelSlug)->firstOrFail()->id)
        ->where('semester_id', Semester::query()->where('slug', $semesterSlug)->firstOrFail()->id)
        ->firstOrFail();

    return CourseOffering::query()->create([
        'student_set_id' => $set->id,
        'course_id' => $course->id,
        'academic_session_id' => $session->id,
        'academic_year_semester_id' => $slot->id,
        'course_title' => $course->title,
        'credit_units' => $course->credit_units,
        'category' => $category,
        'archived_at' => null,
    ]);
}
