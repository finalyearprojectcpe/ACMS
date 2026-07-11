<?php

namespace Database\Seeders;

use App\Models\AcademicSession;
use App\Models\AcademicYear;
use App\Models\AcademicYearSemester;
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

        AcademicYear::query()
            ->orderBy('year_number')
            ->each(function (AcademicYear $academicYear): void {
                Semester::query()
                    ->orderBy('sort_order')
                    ->each(function (Semester $semester) use ($academicYear): void {
                        AcademicYearSemester::query()->updateOrCreate([
                            'academic_year_id' => $academicYear->id,
                            'semester_id' => $semester->id,
                        ]);
                    });
            });

        AcademicSession::query()
            ->with('semesterInstances')
            ->each(fn (AcademicSession $academicSession) => $academicSession->syncDefaultSemesterInstances());
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
