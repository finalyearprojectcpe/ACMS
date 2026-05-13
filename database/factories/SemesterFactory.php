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
        $name = fake()->unique()->randomElement(['Harmattan', 'Rain']);

        return [
            'name' => $name,
            'slug' => str($name)->slug()->toString(),
            'sort_order' => $name === 'Harmattan' ? 1 : 2,
        ];
    }
}
