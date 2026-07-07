<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['student_semester_result_id', 'result_sheet_course_id', 'grade', 'grade_point', 'quality_points'])]
class StudentCourseResult extends Model
{
    /**
     * @return BelongsTo<StudentSemesterResult, $this>
     */
    public function studentSemesterResult(): BelongsTo
    {
        return $this->belongsTo(StudentSemesterResult::class);
    }

    /**
     * @return BelongsTo<ResultSheetCourse, $this>
     */
    public function resultSheetCourse(): BelongsTo
    {
        return $this->belongsTo(ResultSheetCourse::class);
    }
}
