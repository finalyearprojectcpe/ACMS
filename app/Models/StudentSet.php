<?php

namespace App\Models;

use Database\Factories\StudentSetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'advisor_profile_id',
    'entry_academic_session_id',
    'name',
    'minimum_years_to_degree',
    'maximum_course_units',
    'status',
])]
class StudentSet extends Model
{
    /** @use HasFactory<StudentSetFactory> */
    use HasFactory;

    /** @return BelongsTo<AdvisorProfile, $this> */
    public function advisorProfile(): BelongsTo
    {
        return $this->belongsTo(AdvisorProfile::class);
    }

    /** @return BelongsTo<AcademicSession, $this> */
    public function entryAcademicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'entry_academic_session_id');
    }

    /** @return HasMany<StudentProfile, $this> */
    public function students(): HasMany
    {
        return $this->hasMany(StudentProfile::class);
    }

    /** @return HasMany<CourseOffering, $this> */
    public function courseOfferings(): HasMany
    {
        return $this->hasMany(CourseOffering::class);
    }

    /** @return HasMany<Due, $this> */
    public function dues(): HasMany
    {
        return $this->hasMany(Due::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'minimum_years_to_degree' => 'integer',
            'maximum_course_units' => 'integer',
        ];
    }
}
