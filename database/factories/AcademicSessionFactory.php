<?php

namespace Database\Factories;

use App\Models\AcademicSession;
use App\Models\AdvisorProfile;
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
            'advisor_profile_id' => AdvisorProfile::factory(),
            'name' => "{$startYear}/".($startYear + 1),
            'starts_on' => "{$startYear}-09-01",
            'ends_on' => ($startYear + 1).'-08-31',
        ];
    }
}
