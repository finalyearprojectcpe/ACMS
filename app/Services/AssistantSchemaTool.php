<?php

namespace App\Services;

use App\Models\AdvisorProfile;
use App\Models\StudentProfile;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class AssistantSchemaTool implements Tool
{
    public function __construct(
        private readonly AdvisorProfile|StudentProfile $owner,
        private readonly AssistantReportCatalog $catalog,
    ) {}

    public function description(): Stringable|string
    {
        return 'Describe the authorized reporting datasets, field types and meanings. Use when the question needs fields or relationships you have not inspected.';
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return ['dataset' => $schema->string()];
    }

    public function handle(Request $request): Stringable|string
    {
        $datasets = $this->catalog->datasets($this->owner);
        $requested = (string) ($request['dataset'] ?? '');
        if ($requested !== '') {
            $datasets = isset($datasets[$requested]) ? [$requested => $datasets[$requested]] : [];
        }

        return json_encode(['datasets' => $datasets], JSON_THROW_ON_ERROR);
    }
}
