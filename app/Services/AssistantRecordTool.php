<?php

namespace App\Services;

use App\Models\AdvisorProfile;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class AssistantRecordTool implements Tool
{
    /** @var array<string, array{title: string, url: string}> */
    private array $sources = [];

    public function __construct(
        private readonly AdvisorProfile $advisor,
        private readonly AssistantRecordSearch $records,
    ) {}

    public function description(): Stringable|string
    {
        return 'Look up individual adviser records and their source links: sets, students, join requests, course plans, grades, dues, and payments. For roster-wide counts, rankings, comparisons, or grouped questions, use the reporting tool instead.';
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'category' => $schema->string()->enum(['sets', 'students', 'join_requests', 'registrations', 'catalog', 'courses', 'results', 'performance', 'dues', 'due_assignments', 'payments'])->required(),
            'term' => $schema->string(),
            'status' => $schema->string()->enum(['any', 'draft', 'assigned', 'pending', 'approved', 'rejected', 'paid', 'unpaid', 'waived', 'published', 'unpublished', 'initialized', 'success', 'failed', 'abandoned', 'reversed']),
            'set' => $schema->string(),
            'session' => $schema->string(),
            'semester' => $schema->string()->enum(['first', 'second']),
            'grade' => $schema->string()->enum(['A', 'B', 'C', 'D', 'E', 'F', 'missing']),
            'page' => $schema->integer()->min(1),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        $result = $this->records->search($this->advisor, $request->all());

        foreach ($result['records'] as $record) {
            $this->sources[$record['url']] = ['title' => $record['title'], 'url' => $record['url']];
        }

        return json_encode($result, JSON_THROW_ON_ERROR);
    }

    /** @return list<array{title: string, url: string}> */
    public function sources(): array
    {
        return array_values($this->sources);
    }
}
