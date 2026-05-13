<?php

namespace App\Models;

use Database\Factories\CourseTimetableSlotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['course_offering_id', 'day_of_week', 'starts_at', 'ends_at', 'venue'])]
class CourseTimetableSlot extends Model
{
    /** @use HasFactory<CourseTimetableSlotFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<CourseOffering, $this>
     */
    public function courseOffering(): BelongsTo
    {
        return $this->belongsTo(CourseOffering::class);
    }
}
