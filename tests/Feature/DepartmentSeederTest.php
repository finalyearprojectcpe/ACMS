<?php

use App\Models\Department;
use App\Models\Faculty;
use Database\Seeders\FacultySeeder;
use Inertia\Testing\AssertableInertia as Assert;

test('faculty seeder creates grouped departments with unique abbreviations', function () {
    $this->seed(FacultySeeder::class);

    $departmentAbbreviations = Department::query()->pluck('abbreviation');

    expect(Faculty::query()->count())->toBe(12)
        ->and(Department::query()->count())->toBe(59)
        ->and($departmentAbbreviations->unique()->count())->toBe(
            $departmentAbbreviations->count(),
        )
        ->and(
            Department::query()
                ->where('name', 'Computer Engineering')
                ->where('abbreviation', 'CPE')
                ->exists(),
        )
        ->toBeTrue()
        ->and(
            Department::query()
                ->where('name', 'Mechatronics Engineering')
                ->where('abbreviation', 'MCE')
                ->exists(),
        )
        ->toBeTrue();
});

test('signup page receives departments grouped by faculty', function () {
    $this->seed(FacultySeeder::class);

    $response = $this->get(route('signup'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('signup')
        ->has('accountTypes', 2)
        ->has('departments', 12)
        ->where('departments.0.abbreviation', 'SAAT')
        ->where('departments.0.departments.0.name', 'Agribusiness')
        ->where('departments.0.departments.0.abbreviation', 'AGR')
        ->etc());
});
