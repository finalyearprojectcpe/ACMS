<?php

namespace Database\Factories;

use App\Models\AcademicSession;
use App\Models\AcademicYear;
use App\Models\StudentSet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AcademicSession>
 */
class AcademicSessionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startYear = fake()->unique()->numberBetween(2020, 2030);

        return [
            'student_set_id' => StudentSet::factory(),
            'academic_year_id' => fn (): int => AcademicYear::query()->orderBy('year_number')->value('id')
                ?? AcademicYear::factory()->create()->id,
            'start_year' => $startYear,
            'end_year' => $startYear + 1,
        ];
    }
}
