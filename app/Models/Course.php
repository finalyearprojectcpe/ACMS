<?php

namespace App\Models;

use Database\Factories\CourseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['student_set_id', 'code', 'title', 'credit_units', 'is_elective'])]
class Course extends Model
{
    /** @use HasFactory<CourseFactory> */
    use HasFactory;

    public function urlCode(): string
    {
        return strtolower(str_replace(' ', '', $this->code));
    }

    public static function normalizeCode(string $code): string
    {
        $code = Str::upper(Str::squish($code));

        return preg_replace('/^((?:[A-Z]{2,10}-)?[A-Z]{2,10})\s*([0-9]{2,4}[A-Z]?)$/', '$1 $2', $code);
    }

    public static function codeFromUrl(string $urlCode): ?string
    {
        if (preg_match('/^((?:[a-z]{2,10}-)?[a-z]{2,10})([0-9]{2,4}[a-z]?)$/', $urlCode, $matches) !== 1) {
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

    protected function casts(): array
    {
        return ['credit_units' => 'integer', 'is_elective' => 'boolean'];
    }
}
