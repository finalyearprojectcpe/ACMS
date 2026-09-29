<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Database\Factories\AcademicSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'student_set_id',
    'academic_year_id',
    'start_year',
    'end_year',
])]
class AcademicSession extends Model
{
    /** @use HasFactory<AcademicSessionFactory> */
    use HasFactory;

    use HasPublicId;

    /** @return BelongsTo<StudentSet, $this> */
    public function studentSet(): BelongsTo
    {
        return $this->belongsTo(StudentSet::class);
    }

    /** @return BelongsTo<AcademicYear, $this> */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * @return HasMany<AcademicSessionSemester, $this>
     */
    public function semesterInstances(): HasMany
    {
        return $this->hasMany(AcademicSessionSemester::class);
    }

    public function label(): string
    {
        return "{$this->start_year}/{$this->end_year}";
    }

    public function routeYears(): string
    {
        return "{$this->start_year}{$this->end_year}";
    }

    public function hasAcademicRecords(): bool
    {
        return $this->semesterInstances()
            ->where(fn ($query) => $query
                ->whereHas('courseOfferings')
                ->orWhereHas('courseRegistrations'))
            ->exists();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_year' => 'integer',
            'end_year' => 'integer',
        ];
    }
}
