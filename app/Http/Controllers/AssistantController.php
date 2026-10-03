<?php

namespace App\Http\Controllers;

use App\Jobs\AnswerAssistantQuestion;
use App\Models\AssistantConversation;
use App\Models\AssistantMessage;
use App\Models\StudentProfile;
use App\Services\AssistantService;
use App\Services\DashboardAccess;
use App\Services\StudentResultAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class AssistantController extends Controller
{
    public function __construct(
        private readonly DashboardAccess $access,
        private readonly AssistantService $assistant,
        private readonly StudentResultAccess $resultAccess,
    ) {}

    public function store(Request $request): RedirectResponse
    {
        return $this->ask($request);
    }

    public function reply(Request $request, AssistantConversation $assistantConversation): RedirectResponse
    {
        return $this->ask($request, $assistantConversation);
    }

    public function retry(Request $request, AssistantConversation $assistantConversation): RedirectResponse
    {
        $this->authorizeConversation($request, $assistantConversation);
        [$answer, $question] = DB::transaction(function () use ($assistantConversation): array {
            AssistantConversation::query()->whereKey($assistantConversation->id)->lockForUpdate()->firstOrFail();
            $answer = $assistantConversation->messages()->where('role', 'assistant')->latest('id')->firstOrFail();
            abort_unless($answer->status === AssistantMessage::StatusFailed, 409);
            $question = $assistantConversation->messages()->where('role', 'user')
                ->where('id', '<', $answer->id)->latest('id')->firstOrFail();

            $answer->update([
                'status' => AssistantMessage::StatusQueued,
                'content' => '', 'sources' => null, 'error_message' => null,
            ]);

            return [$answer, $question];
        });
        $this->dispatchAnswer($answer, $question);

        return to_route('dashboard.assistant.show', ['assistantConversation' => $assistantConversation]);
    }

    private function ask(Request $request, ?AssistantConversation $conversation = null): RedirectResponse
    {
        $user = $this->access->user($request);
        $owner = $user->isAdviser() ? $this->access->advisor($request) : $this->access->student($request);
        if ($conversation !== null) {
            $this->authorizeConversation($request, $conversation);
        }
        $data = $request->validate(['question' => 'required|string|min:3|max:1500']);

        if (! $this->assistant->available()) {
            return back()->with('error', 'Ask AI is not available yet.');
        }

        $created = DB::transaction(function () use (&$conversation, $owner, $data): ?array {
            if ($conversation === null) {
                $conversation = $owner->assistantConversations()->create([
                    'title' => mb_substr(trim($data['question']), 0, 90),
                ]);
            } else {
                AssistantConversation::query()->whereKey($conversation->id)->lockForUpdate()->firstOrFail();
                if ($conversation->messages()->where('role', 'assistant')
                    ->whereIn('status', [AssistantMessage::StatusQueued, AssistantMessage::StatusRunning])->exists()) {
                    return null;
                }
            }

            $question = $conversation->messages()->create(['role' => 'user', 'content' => $data['question']]);
            $answer = $conversation->messages()->create([
                'role' => 'assistant', 'content' => '', 'status' => AssistantMessage::StatusQueued,
            ]);
            $conversation->touch();

            return [$question, $answer];
        });
        if ($created !== null) {
            [$question, $answer] = $created;
            $this->dispatchAnswer($answer, $question);
        }

        return to_route('dashboard.assistant.show', ['assistantConversation' => $conversation]);
    }

    private function dispatchAnswer(AssistantMessage $answer, AssistantMessage $question): void
    {
        try {
            AnswerAssistantQuestion::dispatch($answer->id, $question->id);
        } catch (Throwable $exception) {
            report($exception);
            $answer->update(['status' => AssistantMessage::StatusFailed,
                'error_message' => 'The assistant could not start. Please try again.']);
        }
    }

    private function authorizeConversation(Request $request, AssistantConversation $conversation): void
    {
        $user = $this->access->user($request);
        $owner = $user->isAdviser() ? $this->access->advisor($request) : $this->access->student($request);
        $ownerColumn = $user->isAdviser() ? 'advisor_profile_id' : 'student_profile_id';
        abort_if((int) $conversation->{$ownerColumn} !== (int) $owner->id, 404);
        if ($owner instanceof StudentProfile) {
            $lockAt = $this->resultAccess->latestResultLockAt($owner);
            abort_if($lockAt !== null && $conversation->created_at->lessThanOrEqualTo($lockAt), 404);
        }
    }
}
