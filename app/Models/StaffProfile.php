<?php

namespace App\Models;

use Database\Factories\StaffProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['user_id', 'department_id'])]
class StaffProfile extends Model
{
    /** @use HasFactory<StaffProfileFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return HasOne<Department, $this>
     */
    public function headedDepartment(): HasOne
    {
        return $this->hasOne(Department::class, 'hod_staff_profile_id');
    }

    /**
     * @return HasOne<Faculty, $this>
     */
    public function headedFaculty(): HasOne
    {
        return $this->hasOne(Faculty::class, 'head_staff_profile_id');
    }

    /**
     * @return HasOne<Faculty, $this>
     */
    public function pendingHeadedFaculty(): HasOne
    {
        return $this->hasOne(Faculty::class, 'pending_head_staff_profile_id');
    }

    /**
     * @return HasMany<DepartmentLevelStaffAssignment, $this>
     */
    public function levelAssignments(): HasMany
    {
        return $this->hasMany(DepartmentLevelStaffAssignment::class);
    }

    /**
     * @return BelongsToMany<Level, $this>
     */
    public function assignedLevels(): BelongsToMany
    {
        return $this->belongsToMany(Level::class, 'department_level_staff_assignments')
            ->withPivot('department_id')
            ->withTimestamps();
    }

    public function isHeadOfDepartment(): bool
    {
        return $this->headedDepartment()->exists();
    }

    public function isFacultyHead(): bool
    {
        return $this->headedFaculty()->exists();
    }
}
