<?php

namespace Database\Seeders;

use App\Models\Level;
use App\Models\Semester;
use Illuminate\Database\Seeder;

class AcademicStructureSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (range(1, 7) as $level) {
            Level::query()->updateOrCreate(
                ['id' => $level],
                [
                    'name' => "{$level}00 Level",
                    'slug' => "{$level}00-level",
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
                'name' => 'Harmattan',
                'slug' => 'harmattan',
                'sort_order' => 1,
            ],
            [
                'name' => 'Rain',
                'slug' => 'rain',
                'sort_order' => 2,
            ],
        ];
    }
}
