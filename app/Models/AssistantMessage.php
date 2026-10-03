<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['assistant_conversation_id', 'role', 'content', 'sources', 'status', 'error_message'])]
class AssistantMessage extends Model
{
    public const StatusQueued = 'queued';

    public const StatusRunning = 'running';

    public const StatusCompleted = 'completed';

    public const StatusFailed = 'failed';

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AssistantConversation::class, 'assistant_conversation_id');
    }

    protected function casts(): array
    {
        return ['sources' => 'array'];
    }
}
