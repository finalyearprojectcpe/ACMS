<?php

namespace App\Models;

use Database\Factories\CourseRegistrationItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['course_registration_id', 'course_offering_id', 'is_elective', 'course_code', 'course_title', 'credit_units', 'grade', 'graded_at'])]
class CourseRegistrationItem extends Model
{
    /** @use HasFactory<CourseRegistrationItemFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<CourseRegistration, $this>
     */
    public function courseRegistration(): BelongsTo
    {
        return $this->belongsTo(CourseRegistration::class);
    }

    /**
     * @return BelongsTo<CourseOffering, $this>
     */
    public function courseOffering(): BelongsTo
    {
        return $this->belongsTo(CourseOffering::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_elective' => 'boolean',
            'graded_at' => 'datetime',
        ];
    }
}
