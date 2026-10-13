<?php

namespace App\Services;

use App\Grade;
use App\Models\StudentSet;

class SetResultsExport
{
    public function __construct(
        private readonly SetResultsData $results,
        private readonly ResultService $resultService,
    ) {}

    /** @return array<string, mixed> */
    public function build(StudentSet $set, ?string $sessionYears, ?string $semester, bool $all): array
    {
        $matrix = $this->results->matrix($set, $sessionYears, $semester, $all);
        $periodDetails = collect($matrix['periods'])->flatMap(fn (array $session) => collect($session['semesters'])->map(
            fn (array $period): array => [
                'id' => $period['id'],
                'level' => $session['level']['title'],
                'session' => $session['name'],
                'semester' => $period['name'],
            ],
        ))->keyBy('id');
        $groups = collect($matrix['columns'])->groupBy('periodId')->map(function ($columns, $periodId) use ($periodDetails): array {
            $first = $columns->first();

            return [
                'id' => (int) $periodId,
                'label' => $first['group'],
                'session' => $first['session'],
                ...$periodDetails->get((int) $periodId, []),
                'columns' => $columns->values()->all(),
            ];
        })->values()->all();
        $periodOrder = [];
        foreach ($matrix['periods'] as $session) {
            foreach ($session['semesters'] as $period) {
                $periodOrder[$period['id']] = count($periodOrder);
            }
        }
        $firstPeriodOrder = $periodOrder[$groups[0]['id'] ?? null] ?? PHP_INT_MAX;

        $profiles = $set->students()->select(['id', 'registration_number'])->get();
        $profileIds = $profiles->pluck('id', 'registration_number');
        $summaries = $this->resultService->summaryRowsForStudents($profiles->pluck('id'));

        return [
            'set' => $set->label(),
            'asOf' => now()->format('j F Y'),
            'groups' => $groups,
            'students' => collect($matrix['students'])->map(function (array $student) use ($groups, $profileIds, $summaries, $periodOrder, $firstPeriodOrder): array {
                $studentSummaries = $summaries->get($profileIds->get($student['registrationNumber']), collect());
                $periodSummaries = $studentSummaries->keyBy('periodId');
                $starting = ['points' => 0, 'units' => 0, 'blocked' => false];
                foreach ($studentSummaries as $summary) {
                    if (($periodOrder[$summary['periodId']] ?? PHP_INT_MAX) >= $firstPeriodOrder) {
                        break;
                    }

                    if (! $summary['counted']) {
                        $starting['blocked'] = true;

                        continue;
                    }

                    $graded = $summary['items']->filter(fn (object $item): bool => $item->grade !== null);
                    $starting['points'] += (int) $graded->sum(fn (object $item): int => Grade::from($item->grade)->points() * (int) $item->credit_units);
                    $starting['units'] += (int) $graded->sum('credit_units');
                }

                return [
                    'name' => $student['name'],
                    'registrationNumber' => $student['registrationNumber'],
                    'starting' => $starting,
                    'periods' => collect($groups)->mapWithKeys(function (array $group) use ($student, $periodSummaries): array {
                        $cells = collect($group['columns'])->map(function (array $column) use ($student): string {
                            $cell = $student['cells'][$column['id']] ?? null;

                            return match ($cell['state'] ?? null) {
                                'published' => $cell['grade'],
                                'missing' => 'MS',
                                'unpublished' => '*',
                                'awaiting' => '·',
                                default => '—',
                            };
                        })->all();
                        $tones = collect($group['columns'])->map(function (array $column) use ($student): string {
                            if (($student['cells'][$column['id']]['assignmentOrigin'] ?? null) === 'carryover' || $column['carryoverOnly']) {
                                return 'carryover';
                            }

                            return $column['isElective'] ? 'elective' : 'normal';
                        })->all();
                        $summary = $periodSummaries->get($group['id']);
                        $graded = $summary === null ? collect() : $summary['items']->filter(
                            fn (object $item): bool => $item->results_published_at !== null && $item->grade !== null,
                        );

                        return [$group['id'] => [
                            'cells' => $cells,
                            'tones' => $tones,
                            'tgp' => (int) $graded->sum(fn (object $item): int => Grade::from($item->grade)->points() * (int) $item->credit_units),
                            'tnu' => (int) $graded->sum('credit_units'),
                            'gpa' => $summary['gpa'] ?? null,
                            'cgpa' => $summary['cgpa'] ?? null,
                        ]];
                    })->all(),
                ];
            })->all(),
        ];
    }
}
