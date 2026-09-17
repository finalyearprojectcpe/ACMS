<?php

namespace Database\Factories;

use App\Models\AcademicSession;
use App\Models\AdvisorProfile;
use App\Models\StudentSet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentSet>
 */
class StudentSetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'advisor_profile_id' => fn (array $attributes): int => AdvisorProfile::factory()->create()->id,
            'entry_academic_session_id' => fn (array $attributes): int => AcademicSession::factory()->create([
                'advisor_profile_id' => (int) ($attributes['advisor_profile_id'] ?? AdvisorProfile::query()->first()->id),
            ])->id,
            'name' => fn (array $attributes): string => AcademicSession::query()
                ->find((int) ($attributes['entry_academic_session_id'] ?? 0))?->name.' Entry' ?? 'Entry Set',
            'minimum_years_to_degree' => fn (array $attributes): int => (int) (AdvisorProfile::query()
                ->find((int) ($attributes['advisor_profile_id'] ?? 0))?->minimum_years_to_degree ?? 4),
            'maximum_course_units' => fn (array $attributes): int => (int) (AdvisorProfile::query()
                ->find((int) ($attributes['advisor_profile_id'] ?? 0))?->maximum_course_units ?? 24),
            'status' => 'active',
        ];
    }
}
