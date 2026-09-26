<?php

namespace App\Models;

use Database\Factories\CourseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['student_set_id', 'code', 'title', 'slug'])]
class Course extends Model
{
    /** @use HasFactory<CourseFactory> */
    use HasFactory;

    public function urlCode(): string
    {
        return strtolower(str_replace(' ', '', $this->code));
    }

    public static function codeFromUrl(string $urlCode): ?string
    {
        if (preg_match('/^([a-z]{2,10})([0-9]{2,4}[a-z]?)$/', $urlCode, $matches) !== 1) {
            return null;
        }

        return strtoupper($matches[1].' '.$matches[2]);
    }

    /** @return BelongsTo<StudentSet, $this> */
    public function studentSet(): BelongsTo
    {
        return $this->belongsTo(StudentSet::class);
    }

    /**
     * @return HasMany<CourseOffering, $this>
     */
    public function offerings(): HasMany
    {
        return $this->hasMany(CourseOffering::class);
    }
}
