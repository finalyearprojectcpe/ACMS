<?php

namespace Database\Factories;

use App\Models\AdvisorProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AdvisorProfile>
 */
class AdvisorProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $abbreviation = fake()->unique()->lexify('???');

        return [
            'user_id' => User::factory()->adviser(),
            'institution_name' => fake()->company(),
            'programme_name' => Str::title("{$abbreviation} Engineering"),
            'programme_abbreviation' => $abbreviation,
            'minimum_years_to_degree' => 4,
            'maximum_course_units' => 24,
            'timezone' => 'Africa/Lagos',
        ];
    }
}
