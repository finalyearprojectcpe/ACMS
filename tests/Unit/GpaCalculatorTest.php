<?php

use App\Services\GpaCalculator;

test('it calculates weighted semester gpa from letter grades', function () {
    $calculator = new GpaCalculator;

    $summary = $calculator->semesterSummary([
        ['credit_units' => 3, 'grade' => 'A'],
        ['credit_units' => 2, 'grade' => 'C'],
        ['credit_units' => 3, 'grade' => 'F'],
    ]);

    expect($summary)->toBe([
        'total_units' => 8,
        'total_grade_points' => 21,
        'gpa' => 2.63,
    ]);
});

test('f is the only zero point carryover grade', function () {
    $calculator = new GpaCalculator;

    expect($calculator->gradePoint('A'))->toBe(5)
        ->and($calculator->gradePoint('B'))->toBe(4)
        ->and($calculator->gradePoint('C'))->toBe(3)
        ->and($calculator->gradePoint('D'))->toBe(2)
        ->and($calculator->gradePoint('E'))->toBe(1)
        ->and($calculator->gradePoint('F'))->toBe(0);
});
