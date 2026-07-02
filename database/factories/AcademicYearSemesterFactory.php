<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\AcademicYearSemester;
use App\Models\Semester;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AcademicYearSemester>
 */
class AcademicYearSemesterFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'academic_year_id' => AcademicYear::factory(),
            'semester_id' => Semester::factory(),
        ];
    }
}
