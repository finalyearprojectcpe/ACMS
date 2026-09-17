<?php

namespace Database\Factories;

use App\Models\AdvisorProfile;
use App\Models\ImportBatch;
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
            'advisor_profile_id' => AdvisorProfile::factory(),
            'uploaded_by_user_id' => User::factory()->adviser(),
            'type' => 'student_provisioning',
            'import_key' => fake()->uuid(),
            'status' => 'draft',
            'file_name' => fake()->word().'.xlsx',
            'mappings' => [],
            'summary' => [],
        ];
    }
}
