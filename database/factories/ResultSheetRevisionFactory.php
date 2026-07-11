<?php

namespace Database\Factories;

use App\Models\ResultSheet;
use App\Models\ResultSheetRevision;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResultSheetRevision>
 */
class ResultSheetRevisionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'result_sheet_id' => ResultSheet::factory(),
            'actor_user_id' => null,
            'revision_number' => 1,
            'action' => 'draft_saved',
            'column_mappings' => [],
            'snapshot' => ['courses' => [], 'students' => []],
        ];
    }
}
