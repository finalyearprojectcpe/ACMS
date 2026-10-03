<?php

namespace App\Services;

use App\Models\AdvisorProfile;
use App\Models\AssistantConversation;
use App\Models\AssistantMessage;
use App\Models\StudentProfile;
use Laravel\Ai\Messages\Message;
use RuntimeException;

class AssistantService
{
    public function available(): bool
    {
        return filled(config('ai.providers.openrouter.key'));
    }

    /** @return array{content: string, sources: list<array{title: string, url: string, records?: int}>} */
    public function answer(AdvisorProfile|StudentProfile $owner, AssistantConversation $conversation, AssistantMessage $question): array
    {
        if (! $this->available()) {
            throw new RuntimeException('Ask AI is not configured yet.');
        }

        $history = $conversation->messages()->where('id', '<', $question->id)
            ->where(fn ($query) => $query->where('role', 'user')->orWhere('status', AssistantMessage::StatusCompleted))
            ->latest('id')->limit(8)->get()->reverse()->values()
            ->map(fn ($message): Message => new Message($message->role, $message->content));
        $agent = RecordsAssistant::make(owner: $owner)->withMessages($history);
        $response = $agent->prompt(
            $question->content,
            provider: 'openrouter',
            model: (string) config('services.assistant.model'),
            timeout: 30,
        );

        if (trim($response->text) === '') {
            throw new RuntimeException('The assistant did not return an answer.');
        }

        $content = $response->text;
        $sources = collect($agent->sources())
            ->filter(fn (array $source): bool => ! str_contains($content, $source['url'])
                && (isset($source['records']) || str_contains(mb_strtolower($content), mb_strtolower($source['title']))))
            ->values()->all();

        return ['content' => $content, 'sources' => $sources];
    }
}
