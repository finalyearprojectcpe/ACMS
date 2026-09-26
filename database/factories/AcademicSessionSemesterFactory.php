<?php

namespace Database\Factories;

use App\Models\AcademicSession;
use App\Models\AcademicSessionSemester;
use App\Models\Semester;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AcademicSessionSemester>
 */
class AcademicSessionSemesterFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'academic_session_id' => AcademicSession::factory(),
            'semester_id' => fn (): int => Semester::query()->orderBy('sort_order')->value('id')
                ?? Semester::factory()->create()->id,
            'maximum_course_units' => 24,
            'registration_enabled' => false,
        ];
    }
}
