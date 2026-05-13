<?php

namespace Database\Factories;

use App\Models\Faculty;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Faculty>
 */
class FacultyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);
        $abbreviation = strtoupper(fake()->unique()->lexify('???'));

        return [
            'name' => Str::title($name),
            'abbreviation' => $abbreviation,
            'slug' => Str::slug($name),
        ];
    }
}
