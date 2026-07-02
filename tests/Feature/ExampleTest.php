<?php

use App\Models\School;

function seedDefaultExampleSchool(): void
{
    config(['app.default_school_abbreviation' => 'FUTO']);

    School::factory()->create([
        'abbreviation' => 'FUTO',
    ]);
}

test('returns a successful response', function () {
    $response = $this->get(route('home'));

    $response->assertOk();
});

test('student signup page returns a successful response', function () {
    seedDefaultExampleSchool();

    $response = $this->get(route('signup.student'));

    $response->assertOk();
});

test('staff signup page returns a successful response', function () {
    seedDefaultExampleSchool();

    $response = $this->get(route('signup.staff'));

    $response->assertOk();
});
