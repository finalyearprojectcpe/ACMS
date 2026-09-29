<?php

namespace App\Http\Controllers;

use App\Models\AssistantConversation;
use App\Services\AssistantService;
use App\Services\DashboardAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class AssistantController extends Controller
{
    public function __construct(
        private readonly DashboardAccess $access,
        private readonly AssistantService $assistant,
    ) {}

    public function store(Request $request): RedirectResponse
    {
        return $this->ask($request);
    }

    public function reply(Request $request, AssistantConversation $assistantConversation): RedirectResponse
    {
        return $this->ask($request, $assistantConversation);
    }

    private function ask(Request $request, ?AssistantConversation $conversation = null): RedirectResponse
    {
        $user = $this->access->user($request);
        $owner = $user->isAdviser() ? $this->access->advisor($request) : $this->access->student($request);
        $ownerColumn = $user->isAdviser() ? 'advisor_profile_id' : 'student_profile_id';
        abort_if($conversation !== null && (int) $conversation->{$ownerColumn} !== (int) $owner->id, 404);
        $data = $request->validate(['question' => 'required|string|min:3|max:1500']);

        if (! $this->assistant->available()) {
            return back()->with('error', 'Ask GradeManager is not available yet.');
        }

        $created = $conversation === null;
        $conversation ??= $owner->assistantConversations()->create([
            'title' => mb_substr(trim($data['question']), 0, 90),
        ]);

        try {
            $answer = $this->assistant->answer($owner, $conversation, $data['question']);
            DB::transaction(function () use ($conversation, $data, $answer): void {
                $conversation->messages()->create(['role' => 'user', 'content' => $data['question']]);
                $conversation->messages()->create([
                    'role' => 'assistant',
                    'content' => $answer['content'],
                    'sources' => $answer['sources'],
                ]);
                $conversation->touch();
            });

            return to_route('dashboard.assistant.show', ['assistantConversation' => $conversation]);
        } catch (Throwable $exception) {
            Log::warning('Assistant request failed', [
                'owner_type' => $user->isAdviser() ? 'adviser' : 'student',
                'owner_id' => $owner->id,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
            if ($created) {
                $conversation->delete();
            }

            return back()->with('error', 'The assistant could not answer right now. Please try again.');
        }
    }
}
