<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Faculty;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
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
            'faculty_id' => Faculty::factory(),
            'name' => Str::title($name),
            'abbreviation' => $abbreviation,
            'slug' => Str::slug($name),
        ];
    }
}
