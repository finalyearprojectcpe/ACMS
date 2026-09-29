<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Database\Factories\StudentJoinRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'student_set_id', 'registration_number', 'status', 'submission_number', 'reviewed_by_user_id', 'reviewed_at', 'review_note'])]
class StudentJoinRequest extends Model
{
    public const StatusPending = 'pending';

    public const StatusApproved = 'approved';

    public const StatusRejected = 'rejected';

    /** @use HasFactory<StudentJoinRequestFactory> */
    use HasFactory;

    use HasPublicId;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<StudentSet, $this> */
    public function studentSet(): BelongsTo
    {
        return $this->belongsTo(StudentSet::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
            'submission_number' => 'integer',
        ];
    }
}
