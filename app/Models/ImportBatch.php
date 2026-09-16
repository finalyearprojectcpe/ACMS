<?php

namespace App\Models;

use Database\Factories\ImportBatchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['advisor_profile_id', 'uploaded_by_user_id', 'student_set_id', 'type', 'import_key', 'status', 'file_name', 'mappings', 'summary', 'submitted_at', 'approved_at'])]
class ImportBatch extends Model
{
    /** @use HasFactory<ImportBatchFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<AdvisorProfile, $this>
     */
    public function advisorProfile(): BelongsTo
    {
        return $this->belongsTo(AdvisorProfile::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploadedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }

    /**
     * @return BelongsTo<StudentSet, $this>
     */
    public function studentSet(): BelongsTo
    {
        return $this->belongsTo(StudentSet::class);
    }

    /**
     * @return HasMany<ImportRow, $this>
     */
    public function rows(): HasMany
    {
        return $this->hasMany(ImportRow::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mappings' => 'array',
            'summary' => 'array',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }
}
