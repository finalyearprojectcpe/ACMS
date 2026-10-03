<?php

namespace App\Jobs;

use App\Models\AssistantMessage;
use App\Models\StudentProfile;
use App\Services\AssistantService;
use App\Services\StudentResultAccess;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class AnswerAssistantQuestion implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 210;

    public function __construct(public int $answerId, public int $questionId) {}

    public function handle(AssistantService $assistant, StudentResultAccess $resultAccess): void
    {
        $answer = AssistantMessage::query()->with('conversation.advisorProfile.user', 'conversation.studentProfile.user')
            ->findOrFail($this->answerId);
        if ($answer->status !== AssistantMessage::StatusQueued || $answer->role !== 'assistant') {
            return;
        }

        $question = $answer->conversation->messages()->whereKey($this->questionId)
            ->where('role', 'user')->firstOrFail();
        $owner = $answer->conversation->advisorProfile ?? $answer->conversation->studentProfile;
        if ($owner === null || $owner->user === null || $owner->user->disabled_at !== null
            || ($owner instanceof StudentProfile && $resultAccess->latestResultLockAt($owner)?->greaterThanOrEqualTo($answer->conversation->created_at))) {
            $answer->update(['status' => AssistantMessage::StatusFailed,
                'error_message' => 'This conversation is no longer available.']);

            return;
        }

        $answer->update(['status' => AssistantMessage::StatusRunning, 'error_message' => null]);

        try {
            $result = $assistant->answer($owner, $answer->conversation, $question);
            $answer->update([
                'content' => $result['content'], 'sources' => $result['sources'],
                'status' => AssistantMessage::StatusCompleted,
            ]);
            $answer->conversation->touch();
        } catch (Throwable $exception) {
            Log::warning('Assistant request failed', [
                'answer_id' => $answer->id, 'exception' => $exception::class,
            ]);
            $answer->update(['status' => AssistantMessage::StatusFailed,
                'error_message' => 'The assistant could not answer right now. Please try again.']);
        }
    }

    public function failed(?Throwable $exception): void
    {
        AssistantMessage::query()->whereKey($this->answerId)
            ->whereIn('status', [AssistantMessage::StatusQueued, AssistantMessage::StatusRunning])
            ->update(['status' => AssistantMessage::StatusFailed,
                'error_message' => 'The assistant could not answer right now. Please try again.']);
    }
}
