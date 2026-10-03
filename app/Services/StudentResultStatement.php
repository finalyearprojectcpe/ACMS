<?php

namespace App\Services;

use App\Models\CourseRegistrationItem;
use App\Models\StudentProfile;

class StudentResultStatement
{
    public function __construct(private readonly ResultService $results) {}

    /** @return array<string, mixed> */
    public function build(StudentProfile $student): array
    {
        $student->loadMissing(['user', 'studentSet.advisorProfile.user']);
        $periods = $this->results->studentPeriods($this->results->studentRegistrations($student))
            ->filter(fn (array $period): bool => $period['visibleItems']->isNotEmpty())
            ->map(function (array $period): array {
                $registration = $period['registration'];
                $semester = $registration->academicSessionSemester;
                $items = $period['visibleItems']->sortBy('course_code');

                return [
                    'level' => $semester->academicSession->academicYear->name,
                    'session' => $semester->academicSession->label().' Session',
                    'semester' => $semester->semester->name,
                    'courses' => $items->map(fn (CourseRegistrationItem $item): array => [
                        'code' => $item->course_code,
                        'title' => $item->course_title,
                        'units' => (int) $item->credit_units,
                        'grade' => $item->missing_script ? 'Missing script' : $item->grade,
                    ])->values()->all(),
                    'totalUnits' => (int) $items->sum('credit_units'),
                    'awaitingPublication' => $registration->items->count() - $items->count(),
                    'gpa' => $period['gpa'],
                    'cgpa' => $period['cgpa'],
                ];
            })->values();

        return [
            'student' => [
                'name' => $student->official_name,
                'registrationNumber' => $student->registration_number,
                'registrationRouteValue' => $student->routeRegistrationNumber(),
                'set' => $student->studentSet->label(),
                'adviser' => $student->studentSet->advisorProfile->user->displayName(),
            ],
            'asOf' => now()->format('j F Y'),
            'cgpa' => $periods->last()['cgpa'] ?? null,
            'periods' => $periods->all(),
        ];
    }
}
