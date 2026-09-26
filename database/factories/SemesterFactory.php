<?php

namespace Database\Factories;

use App\Models\Semester;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Semester>
 */
class SemesterFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->randomElement(['1st Semester', '2nd Semester']);

        return [
            'name' => $name,
            'slug' => $name === '1st Semester' ? 'first' : 'second',
            'sort_order' => $name === '1st Semester' ? 1 : 2,
        ];
    }
}
