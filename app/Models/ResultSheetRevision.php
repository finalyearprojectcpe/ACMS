<?php

namespace App\Models;

use Database\Factories\ResultSheetRevisionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'result_sheet_id',
    'actor_user_id',
    'revision_number',
    'action',
    'source_file_disk',
    'source_file_path',
    'source_file_name',
    'source_file_checksum',
    'column_mappings',
    'snapshot',
    'note',
])]
class ResultSheetRevision extends Model
{
    /** @use HasFactory<ResultSheetRevisionFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $hidden = ['source_file_disk', 'source_file_path'];

    /**
     * @return BelongsTo<ResultSheet, $this>
     */
    public function resultSheet(): BelongsTo
    {
        return $this->belongsTo(ResultSheet::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'revision_number' => 'integer',
            'column_mappings' => 'array',
            'snapshot' => 'array',
        ];
    }
}
