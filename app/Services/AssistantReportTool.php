<?php

namespace App\Services;

use App\Models\AdvisorProfile;
use App\Models\StudentProfile;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;
use Throwable;

class AssistantReportTool implements Tool
{
    /** @var array<string, array{title: string, url: string, records?: int}> */
    private array $sources = [];

    private int $calls = 0;

    public function __construct(
        private readonly AdvisorProfile|StudentProfile $owner,
        private readonly AssistantReportQuery $reports,
    ) {}

    public function description(): Stringable|string
    {
        return 'Query authorized ACMS records with filters, complete-population sorting, grouping and aggregates. Use this for counts, rankings, comparisons and cross-record questions. Call the schema tool to discover fields.';
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'dataset' => $schema->string()->enum([
                'sets', 'students', 'courses', 'offerings', 'registrations', 'results',
                'dues', 'due_assignments', 'payments', 'join_requests',
            ])->required(),
            'select' => $schema->array()->items($schema->string()),
            'filters' => $schema->array()->max(8)->items($schema->object([
                'field' => $schema->string()->required(),
                'operator' => $schema->string()->enum([
                    'eq', 'ne', 'contains', 'gt', 'gte', 'lt', 'lte', 'in', 'is_null', 'not_null',
                ])->required(),
                'value' => $schema->string(),
            ])),
            'group_by' => $schema->string(),
            'aggregate' => $schema->string()->enum(['count', 'sum', 'avg', 'min', 'max']),
            'aggregate_field' => $schema->string(),
            'sort_by' => $schema->string(),
            'direction' => $schema->string()->enum(['asc', 'desc']),
            'limit' => $schema->integer()->min(1)->max(50),
            'page' => $schema->integer()->min(1)->max(100),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        if (++$this->calls > 8) {
            return json_encode(['error' => 'Query limit reached. Answer with the evidence already available.'], JSON_THROW_ON_ERROR);
        }

        try {
            $result = $this->reports->run($this->owner, $request->all());
        } catch (Throwable $exception) {
            if (! $exception instanceof \InvalidArgumentException && ! $exception instanceof \RuntimeException) {
                throw $exception;
            }

            return json_encode(['error' => $exception->getMessage()], JSON_THROW_ON_ERROR);
        }

        foreach ($result['rows'] as $row) {
            if (filled($row['source'] ?? null)) {
                $this->sources[$row['source']] = [
                    'title' => (string) ($row['name'] ?? $row['student'] ?? $row['title'] ?? $row['code'] ?? $row['set'] ?? 'Source record'),
                    'url' => $row['source'],
                ];
            }
        }

        foreach ($result['sources'] as $source) {
            $this->sources[$source['url']] = $source;
        }

        return json_encode($result, JSON_THROW_ON_ERROR);
    }

    /** @return list<array{title: string, url: string, records?: int}> */
    public function sources(): array
    {
        return array_values($this->sources);
    }
}
