<?php

namespace App\Services;

use App\Models\AdvisorProfile;
use App\Models\StudentProfile;
use App\Models\StudentSet;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class AssistantReportQuery
{
    public function __construct(
        private readonly AssistantReportCatalog $catalog,
        private readonly AssistantReportData $data,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function run(AdvisorProfile|StudentProfile $owner, array $input): array
    {
        $dataset = (string) ($input['dataset'] ?? '');
        $definition = $this->catalog->datasets($owner)[$dataset] ?? null;
        if ($definition === null) {
            throw new InvalidArgumentException('That dataset is not available for this account.');
        }

        $fields = $definition['fields'];
        $filters = $input['filters'] ?? [];
        if (! is_array($filters) || count($filters) > 8) {
            throw new InvalidArgumentException('Use at most eight filters.');
        }

        foreach ($filters as $filter) {
            if (! is_array($filter) || ! is_string($filter['field'] ?? null)
                || ! isset($fields[$filter['field']])) {
                throw new InvalidArgumentException('A filter contains an unavailable field.');
            }
            if (! in_array($filter['operator'] ?? '', [
                'eq', 'ne', 'contains', 'gt', 'gte', 'lt', 'lte', 'in', 'is_null', 'not_null',
            ], true)) {
                throw new InvalidArgumentException('A filter contains an unavailable operator.');
            }
            if (! in_array($filter['operator'], ['is_null', 'not_null'], true)
                && (! is_string($filter['value'] ?? null) || mb_strlen($filter['value']) > 200)) {
                throw new InvalidArgumentException('A filter value must be text of at most 200 characters.');
            }
            if (str_starts_with($fields[$filter['field']], 'number')
                && in_array($filter['operator'], ['eq', 'ne', 'gt', 'gte', 'lt', 'lte'], true)
                && ! is_numeric($filter['value'])) {
                throw new InvalidArgumentException('Numeric fields require numeric filter values.');
            }
        }

        $selected = $input['select'] ?? array_keys($fields);
        if (! is_array($selected) || count($selected) > 16
            || collect($selected)->contains(fn ($field): bool => ! is_string($field) || ! isset($fields[$field]))) {
            throw new InvalidArgumentException('The selected fields are not available.');
        }
        $selected = array_values(array_unique($selected));
        if (isset($input['group_by']) && ! is_string($input['group_by'])) {
            throw new InvalidArgumentException('The grouping field must be text.');
        }
        $groupBy = $input['group_by'] ?? '';
        if ($groupBy !== '' && ! isset($fields[$groupBy])) {
            throw new InvalidArgumentException('The grouping field is not available.');
        }
        if (isset($input['aggregate']) && ! is_string($input['aggregate'])) {
            throw new InvalidArgumentException('The aggregate must be text.');
        }
        $aggregate = $input['aggregate'] ?? '';
        if (! in_array($aggregate, ['', 'count', 'sum', 'avg', 'min', 'max'], true)) {
            throw new InvalidArgumentException('The aggregate is not available.');
        }
        if (isset($input['aggregate_field']) && ! is_string($input['aggregate_field'])) {
            throw new InvalidArgumentException('The aggregate field must be text.');
        }
        $aggregateField = $input['aggregate_field'] ?? '';
        if (in_array($aggregate, ['sum', 'avg', 'min', 'max'], true)
            && (! isset($fields[$aggregateField]) || ! str_starts_with($fields[$aggregateField], 'number'))) {
            throw new InvalidArgumentException('That aggregate requires a numeric field.');
        }
        if ($groupBy !== '' && $aggregate === '') {
            throw new InvalidArgumentException('Choose an aggregate when grouping records.');
        }

        if (isset($input['sort_by']) && ! is_string($input['sort_by'])) {
            throw new InvalidArgumentException('The sort field must be text.');
        }
        $sortBy = $input['sort_by'] ?? '';
        if ($sortBy !== '' && ! isset($fields[$sortBy]) && ! ($aggregate !== '' && $sortBy === 'value')) {
            throw new InvalidArgumentException('The sort field is not available.');
        }
        if ($aggregate !== '' && $sortBy !== '' && ! in_array($sortBy, ['value', $groupBy], true)) {
            throw new InvalidArgumentException('Aggregated rows can only be sorted by group or value.');
        }
        $direction = $input['direction'] ?? 'asc';
        if (! in_array($direction, ['asc', 'desc'], true)) {
            throw new InvalidArgumentException('Sort direction must be asc or desc.');
        }
        $limit = min(50, max(1, filter_var($input['limit'] ?? 20, FILTER_VALIDATE_INT) ?: 20));
        $page = min(100, max(1, filter_var($input['page'] ?? 1, FILTER_VALIDATE_INT) ?: 1));

        $allRows = $this->data->rows($owner, $dataset);
        $rows = $allRows->filter(fn (array $row): bool => collect($filters)
            ->every(fn (array $filter): bool => $this->matches($row, $filter, $fields)))->values();
        $matched = $rows->count();
        $sources = $aggregate === '' ? [] : $this->collectionSources($owner, $dataset, $rows);

        if ($aggregate !== '') {
            $rows = $this->aggregate($rows, $groupBy, $aggregate, $aggregateField);
        }
        if ($sortBy !== '') {
            $rows = $rows->sort(fn (array $first, array $second): int => $this->compare(
                $first[$sortBy] ?? null, $second[$sortBy] ?? null, $direction,
            ) ?: strcmp((string) ($first['registration_number'] ?? $first['group'] ?? $first['_url'] ?? ''),
                (string) ($second['registration_number'] ?? $second['group'] ?? $second['_url'] ?? '')))->values();
        }

        $pageRows = $rows->forPage($page, $limit)->values()->map(function (array $row) use ($selected, $aggregate): array {
            if ($aggregate !== '') {
                return $row;
            }

            return [
                ...array_intersect_key($row, array_flip($selected)),
                'source' => $row['_url'] ?? null,
            ];
        })->all();

        return [
            'dataset' => $dataset,
            'metric' => $aggregate === '' ? null : $aggregate.($aggregateField === '' ? '' : ' '.$aggregateField),
            'matched' => $matched,
            'returned' => count($pageRows),
            'page' => $page,
            'more_available' => $rows->count() > $page * $limit,
            'complete_population' => true,
            'sources' => $sources,
            'rows' => $pageRows,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return list<array{title: string, url: string, records: int}>
     */
    private function collectionSources(AdvisorProfile|StudentProfile $owner, string $dataset, Collection $rows): array
    {
        if ($rows->isEmpty()) {
            return [];
        }

        if ($owner instanceof StudentProfile) {
            $section = match ($dataset) {
                'results' => 'results',
                'due_assignments', 'payments' => 'dues',
                'registrations' => 'courses',
                default => 'profile-settings',
            };

            return [[
                'title' => match ($section) {
                    'results' => 'Your results',
                    'dues' => 'Your dues',
                    'courses' => 'Your courses',
                    default => 'Your profile',
                },
                'url' => route('dashboard.section', ['section' => $section]),
                'records' => $rows->count(),
            ]];
        }

        $counts = $rows->countBy('set');
        $route = match ($dataset) {
            'courses' => 'student-sets.catalog.show',
            'offerings', 'registrations' => 'student-sets.courses.show',
            'results' => 'student-sets.matrix.show',
            'dues', 'due_assignments', 'payments' => 'student-sets.dues.show',
            default => 'student-sets.show',
        };
        $subject = match ($dataset) {
            'students', 'join_requests' => 'Student roster',
            'courses' => 'Course catalog',
            'offerings', 'registrations' => 'Courses',
            'results' => 'Set results',
            'dues', 'due_assignments', 'payments' => 'Student dues',
            default => 'Student set',
        };

        return $owner->studentSets()->get()
            ->filter(fn (StudentSet $set): bool => $counts->has($set->label()))
            ->map(fn (StudentSet $set): array => [
                'title' => $subject.' - '.$set->label(),
                'url' => route($route, $set->routeParameters()),
                'records' => (int) $counts->get($set->label()),
            ])->values()->all();
    }

    /** @param array<string, mixed> $row @param array<string, mixed> $filter @param array<string, string> $fields */
    private function matches(array $row, array $filter, array $fields): bool
    {
        $field = $filter['field'];
        $operator = $filter['operator'];
        $actual = $row[$field] ?? null;
        if ($operator === 'is_null') {
            return $actual === null;
        }
        if ($operator === 'not_null') {
            return $actual !== null;
        }
        $raw = (string) ($filter['value'] ?? '');
        $type = $fields[$field];
        $expected = str_starts_with($type, 'number') && is_numeric($raw) ? (float) $raw
            : (str_starts_with($type, 'boolean') ? filter_var($raw, FILTER_VALIDATE_BOOLEAN) : $raw);
        if ($actual === null) {
            return false;
        }
        if ($operator === 'contains') {
            return str_contains(mb_strtolower((string) $actual), mb_strtolower($raw));
        }
        if ($operator === 'in') {
            return collect(explode(',', $raw))->map(fn (string $value): string => mb_strtolower(trim($value)))
                ->contains(mb_strtolower((string) $actual));
        }
        $comparison = str_starts_with($type, 'number') && is_numeric($actual) && is_numeric($expected)
            ? (float) $actual <=> (float) $expected
            : strcasecmp((string) $actual, (string) $expected);

        return match ($operator) {
            'eq' => $comparison === 0,
            'ne' => $comparison !== 0,
            'gt' => $comparison > 0,
            'gte' => $comparison >= 0,
            'lt' => $comparison < 0,
            'lte' => $comparison <= 0,
        };
    }

    /** @param Collection<int, array<string, mixed>> $rows @return Collection<int, array<string, mixed>> */
    private function aggregate(Collection $rows, string $groupBy, string $aggregate, string $aggregateField): Collection
    {
        $groups = $groupBy === '' ? collect(['all' => $rows]) : $rows->groupBy(fn (array $row): string => (string) ($row[$groupBy] ?? 'Not available'));

        return $groups->map(function (Collection $group, string|int $label) use ($aggregate, $aggregateField): array {
            $numbers = $group->pluck($aggregateField)->filter(fn ($value): bool => is_numeric($value));
            $value = match ($aggregate) {
                'count' => $group->count(),
                'sum' => $numbers->sum(),
                'avg' => $numbers->isEmpty() ? null : round($numbers->avg(), 2),
                'min' => $numbers->isEmpty() ? null : $numbers->min(),
                'max' => $numbers->isEmpty() ? null : $numbers->max(),
            };

            return ['group' => (string) $label, 'value' => $value, 'records' => $group->count()];
        })->values();
    }

    private function compare(mixed $first, mixed $second, string $direction): int
    {
        if ($first === null || $second === null) {
            return $first === $second ? 0 : ($first === null ? 1 : -1);
        }

        $comparison = is_numeric($first) && is_numeric($second)
            ? (float) $first <=> (float) $second
            : strcasecmp((string) $first, (string) $second);

        return $direction === 'desc' ? -$comparison : $comparison;
    }
}
