<?php

use App\CourseCategory;
use App\Models\AcademicSession;
use App\Models\AcademicYear;
use App\Models\AcademicYearSemester;
use App\Models\AdvisorProfile;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\Semester;
use App\Models\StudentProfile;
use App\Models\StudentSet;
use Carbon\CarbonImmutable;
use Database\Seeders\AcademicStructureSeeder;
use Illuminate\Support\Str;

test('a student selects offered courses and the adviser reviews the same registration', function () {
    $this->seed(AcademicStructureSeeder::class);
    $this->travelTo(CarbonImmutable::parse('2026-01-15'));

    $advisor = AdvisorProfile::factory()->create([
        'minimum_years_to_degree' => 4,
        'maximum_course_units' => 24,
    ]);
    $session = AcademicSession::factory()->create([
        'advisor_profile_id' => $advisor->id,
        'name' => '2025/2026',
        'starts_on' => '2025-09-01',
        'ends_on' => '2026-08-31',
    ]);
    $set = StudentSet::factory()->create([
        'advisor_profile_id' => $advisor->id,
        'entry_academic_session_id' => $session->id,
        'minimum_years_to_degree' => 4,
        'maximum_course_units' => 24,
    ]);
    $student = StudentProfile::factory()->create([
        'advisor_profile_id' => $advisor->id,
        'student_set_id' => $set->id,
        'current_academic_year_id' => AcademicYear::query()->where('year_number', 1)->value('id'),
    ]);
    $slot = AcademicYearSemester::query()
        ->whereBelongsTo(AcademicYear::query()->where('year_number', 1)->firstOrFail())
        ->whereBelongsTo(Semester::query()->where('slug', 'harmattan')->firstOrFail())
        ->firstOrFail();
    $normal = registrationOffering($advisor, $set, $session, $slot, 'CPE 101', CourseCategory::Normal, 3);
    $elective = registrationOffering($advisor, $set, $session, $slot, 'CPE 103', CourseCategory::Elective, 2);

    $this->actingAs($student->user)->post(route('dashboard.course-registration.store'), [
        'action' => 'submit',
        'items' => [
            ['course_offering_id' => $normal->id],
            ['course_offering_id' => $elective->id],
        ],
    ])->assertRedirect(route('dashboard.section', ['section' => 'course-registration']));

    $registration = CourseRegistration::query()->with('items')->firstOrFail();

    expect($registration->status)->toBe(CourseRegistration::StatusSubmitted)
        ->and($registration->items)->toHaveCount(2)
        ->and($registration->items->pluck('category')->all())->toBe([
            CourseCategory::Normal->value,
            CourseCategory::Elective->value,
        ]);

    $this->actingAs($advisor->user)
        ->get(route('dashboard.course-registration.show', $registration))
        ->assertOk();

    $this->actingAs($advisor->user)
        ->patch(route('dashboard.course-registration.return', $registration), [
            'feedback_note' => 'Reduce the selected course load.',
        ])
        ->assertRedirect(route('dashboard.course-registration.show', $registration));

    expect($registration->refresh()->status)->toBe(CourseRegistration::StatusReturned)
        ->and($registration->feedback_note)->toBe('Reduce the selected course load.');

    $this->actingAs($student->user)->post(route('dashboard.course-registration.store'), [
        'action' => 'submit',
        'items' => [['course_offering_id' => $normal->id]],
    ]);

    $this->actingAs($advisor->user)
        ->patch(route('dashboard.course-registration.approve', $registration))
        ->assertRedirect(route('dashboard.course-registration.show', $registration));

    expect($registration->refresh()->status)->toBe(CourseRegistration::StatusApproved)
        ->and($registration->items)->toHaveCount(1);
});

function registrationOffering(
    AdvisorProfile $advisor,
    StudentSet $set,
    AcademicSession $session,
    AcademicYearSemester $slot,
    string $code,
    CourseCategory $category,
    int $units,
): CourseOffering {
    $course = Course::query()->create([
        'advisor_profile_id' => $advisor->id,
        'code' => $code,
        'title' => "{$code} Course",
        'slug' => Str::slug($code),
        'credit_units' => $units,
    ]);

    return CourseOffering::query()->create([
        'student_set_id' => $set->id,
        'course_id' => $course->id,
        'academic_session_id' => $session->id,
        'academic_year_semester_id' => $slot->id,
        'course_title' => $course->title,
        'credit_units' => $units,
        'category' => $category,
    ]);
}
