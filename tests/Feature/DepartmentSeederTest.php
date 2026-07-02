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

test('student signup page loads the provisioned account activation form', function () {
    $this->seed(FacultySeeder::class);

    $response = $this->get(route('signup.student'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('signup')
        ->where('accountType', 'student')
        ->etc());
});
