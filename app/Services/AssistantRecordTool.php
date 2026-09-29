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
        return 'Search this course adviser\'s student sets, students, course registrations, course offerings, dues, and completed payments. Use this before answering questions about records.';
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'category' => $schema->string()->enum(['sets', 'students', 'registrations', 'courses', 'dues', 'payments'])->required(),
            'term' => $schema->string()->required(),
            'status' => $schema->string()->enum(['any', 'submitted', 'approved', 'returned'])->required(),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        $result = $this->records->search(
            $this->advisor,
            (string) ($request['category'] ?? ''),
            (string) ($request['term'] ?? ''),
            (string) ($request['status'] ?? 'any'),
        );

        foreach ($result['records'] as $record) {
            $this->sources[$record['url']] = ['title' => $record['title'], 'url' => $record['url']];
        }

        return json_encode($result, JSON_THROW_ON_ERROR);
    }

    /** @return list<array{title: string, url: string}> */
    public function sources(): array
    {
        return array_slice(array_values($this->sources), 0, 6);
    }
}
