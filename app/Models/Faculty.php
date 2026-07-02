<?php

namespace App\Models;

use Database\Factories\FacultyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

#[Fillable(['school_id', 'name', 'abbreviation', 'slug', 'head_staff_profile_id', 'pending_head_staff_profile_id', 'head_transfer_requested_at'])]
class Faculty extends Model
{
    /** @use HasFactory<FacultyFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * @return BelongsTo<StaffProfile, $this>
     */
    public function headStaffProfile(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class, 'head_staff_profile_id');
    }

    /**
     * @return BelongsTo<StaffProfile, $this>
     */
    public function pendingHeadStaffProfile(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class, 'pending_head_staff_profile_id');
    }

    /**
     * @return HasMany<Department, $this>
     */
    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    /**
     * @return MorphMany<CourseOffering, $this>
     */
    public function courseOfferings(): MorphMany
    {
        return $this->morphMany(CourseOffering::class, 'owner');
    }

    public function assignHead(StaffProfile $staffProfile): self
    {
        $this->ensureStaffBelongsToFaculty($staffProfile);

        return DB::transaction(function () use ($staffProfile): self {
            $faculty = self::query()
                ->whereKey($this->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $faculty->forceFill([
                'head_staff_profile_id' => $staffProfile->getKey(),
                'pending_head_staff_profile_id' => null,
                'head_transfer_requested_at' => null,
            ])->save();

            return $this->refresh();
        });
    }

    public function requestHeadTransfer(StaffProfile $staffProfile): self
    {
        $this->ensureStaffBelongsToFaculty($staffProfile);

        return DB::transaction(function () use ($staffProfile): self {
            $faculty = self::query()
                ->whereKey($this->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $faculty->forceFill([
                'pending_head_staff_profile_id' => $staffProfile->getKey(),
                'head_transfer_requested_at' => now(),
            ])->save();

            return $this->refresh();
        });
    }

    public function acceptHeadTransfer(StaffProfile $staffProfile): self
    {
        if ((int) $this->pending_head_staff_profile_id !== (int) $staffProfile->getKey()) {
            throw new InvalidArgumentException('This staff profile is not the pending faculty head.');
        }

        return $this->assignHead($staffProfile);
    }

    private function ensureStaffBelongsToFaculty(StaffProfile $staffProfile): void
    {
        if (! $staffProfile->exists) {
            throw new InvalidArgumentException('The staff profile must exist before it can become faculty head.');
        }

        $staffProfile->loadMissing('department');

        if ((int) $staffProfile->department?->faculty_id !== (int) $this->getKey()) {
            throw new InvalidArgumentException('The faculty head must be a staff member in this faculty.');
        }
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'head_transfer_requested_at' => 'datetime',
        ];
    }
}
