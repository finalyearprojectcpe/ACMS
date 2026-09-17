<?php

namespace Database\Factories;

use App\Models\AdvisorProfile;
use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Course>
 */
class CourseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $prefix = strtoupper(fake()->unique()->lexify('???'));
        $number = fake()->unique()->numberBetween(100, 799);
        $code = "{$prefix} {$number}";
        $title = Str::title(fake()->unique()->words(3, true));

        return [
            'advisor_profile_id' => AdvisorProfile::factory(),
            'code' => $code,
            'title' => $title,
            'slug' => Str::slug("{$code} {$title}"),
            'credit_units' => fake()->numberBetween(1, 4),
        ];
    }
}
