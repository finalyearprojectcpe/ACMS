<?php

namespace Database\Factories;

use App\Models\ImportBatch;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImportBatch>
 */
class ImportBatchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'uploaded_by_user_id' => User::factory()->staff(),
            'type' => 'hod_provisioning',
            'import_key' => fake()->uuid(),
            'status' => 'draft',
            'file_name' => fake()->word().'.xlsx',
            'mappings' => [],
            'summary' => [],
        ];
    }
}
