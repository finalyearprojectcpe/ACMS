<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\AdvisorProfile;
use App\Models\StudentSet;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<StudentSet>
 */
class StudentSetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $departmentName = fake()->randomElement([
            'Computer Engineering',
            'Electrical Engineering',
            'Mechanical Engineering',
        ]);
        $entryStartYear = fake()->numberBetween(2020, 2030);

        return [
            'advisor_profile_id' => AdvisorProfile::factory(),
            'department_name' => $departmentName,
            'department_slug' => Str::slug($departmentName),
            'entry_start_year' => $entryStartYear,
            'entry_end_year' => $entryStartYear + 1,
            'student_signup_enabled' => true,
            'current_academic_year_id' => fn (): int => AcademicYear::query()
                ->where('year_number', 1)
                ->value('id') ?? AcademicYear::factory()->create(['year_number' => 1, 'name' => '100 Level', 'slug' => '100-level'])->id,
        ];
    }
}
