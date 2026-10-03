<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Database\Factories\AcademicSessionSemesterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'academic_session_id',
    'semester_id',
    'maximum_course_units',
])]
class AcademicSessionSemester extends Model
{
    /** @use HasFactory<AcademicSessionSemesterFactory> */
    use HasFactory;

    use HasPublicId;

    /**
     * @return BelongsTo<AcademicSession, $this>
     */
    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    /**
     * @return BelongsTo<Semester, $this>
     */
    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    /** @return HasMany<CourseOffering, $this> */
    public function courseOfferings(): HasMany
    {
        return $this->hasMany(CourseOffering::class);
    }

    /** @return HasMany<CourseRegistration, $this> */
    public function courseRegistrations(): HasMany
    {
        return $this->hasMany(CourseRegistration::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'maximum_course_units' => 'integer',
        ];
    }
}
