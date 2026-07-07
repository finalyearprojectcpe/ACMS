<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['result_sheet_id', 'course_offering_id', 'course_code', 'course_title', 'credit_units', 'sort_order'])]
class ResultSheetCourse extends Model
{
    /**
     * @return BelongsTo<ResultSheet, $this>
     */
    public function resultSheet(): BelongsTo
    {
        return $this->belongsTo(ResultSheet::class);
    }

    /**
     * @return BelongsTo<CourseOffering, $this>
     */
    public function courseOffering(): BelongsTo
    {
        return $this->belongsTo(CourseOffering::class);
    }

    /**
     * @return HasMany<StudentCourseResult, $this>
     */
    public function studentCourseResults(): HasMany
    {
        return $this->hasMany(StudentCourseResult::class);
    }
}
