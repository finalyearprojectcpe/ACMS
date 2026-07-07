<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['result_sheet_id', 'student_profile_id', 'total_units', 'total_grade_points', 'gpa'])]
class StudentSemesterResult extends Model
{
    /**
     * @return BelongsTo<ResultSheet, $this>
     */
    public function resultSheet(): BelongsTo
    {
        return $this->belongsTo(ResultSheet::class);
    }

    /**
     * @return BelongsTo<StudentProfile, $this>
     */
    public function studentProfile(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class);
    }

    /**
     * @return HasMany<StudentCourseResult, $this>
     */
    public function courseResults(): HasMany
    {
        return $this->hasMany(StudentCourseResult::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gpa' => 'decimal:2',
        ];
    }
}
