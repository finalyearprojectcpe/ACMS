<?php

namespace App\Services;

class GpaCalculator
{
    public function gradePoint(string $grade): int
    {
        return match (strtoupper($grade)) {
            'A' => 5,
            'B' => 4,
            'C' => 3,
            'D' => 2,
            'E' => 1,
            default => 0,
        };
    }

    public function qualityPoints(int $creditUnits, string $grade): int
    {
        return $creditUnits * $this->gradePoint($grade);
    }

    /**
     * @param  array<int, array{credit_units: int, grade: string}>  $courses
     * @return array{total_units: int, total_grade_points: int, gpa: float}
     */
    public function semesterSummary(array $courses): array
    {
        $totalUnits = array_sum(array_map(
            fn (array $course): int => (int) $course['credit_units'],
            $courses,
        ));
        $totalGradePoints = array_sum(array_map(
            fn (array $course): int => $this->qualityPoints((int) $course['credit_units'], (string) $course['grade']),
            $courses,
        ));

        return [
            'total_units' => $totalUnits,
            'total_grade_points' => $totalGradePoints,
            'gpa' => $totalUnits === 0 ? 0.0 : round($totalGradePoints / $totalUnits, 2),
        ];
    }

    /**
     * @param  array<int, array{total_units: int, total_grade_points: int}>  $summaries
     */
    public function cumulativeGpa(array $summaries): float
    {
        $totalUnits = array_sum(array_map(
            fn (array $summary): int => (int) $summary['total_units'],
            $summaries,
        ));

        if ($totalUnits === 0) {
            return 0.0;
        }

        $totalGradePoints = array_sum(array_map(
            fn (array $summary): int => (int) $summary['total_grade_points'],
            $summaries,
        ));

        return round($totalGradePoints / $totalUnits, 2);
    }
}
