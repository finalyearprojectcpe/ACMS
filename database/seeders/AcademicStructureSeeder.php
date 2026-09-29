<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Semester;
use Illuminate\Database\Seeder;

class AcademicStructureSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (range(1, 9) as $level) {
            AcademicYear::query()->updateOrCreate(
                ['id' => $level],
                [
                    'name' => "{$level}00 Level",
                    'slug' => "{$level}00-level",
                    'year_number' => $level,
                ],
            );
        }

        foreach ($this->semesters() as $semester) {
            Semester::query()->updateOrCreate(
                ['slug' => $semester['slug']],
                $semester,
            );
        }

    }

    /**
     * @return array<int, array{name: string, slug: string, sort_order: int}>
     */
    private function semesters(): array
    {
        return [
            [
                'name' => '1st Semester',
                'slug' => 'first',
                'sort_order' => 1,
            ],
            [
                'name' => '2nd Semester',
                'slug' => 'second',
                'sort_order' => 2,
            ],
        ];
    }
}
