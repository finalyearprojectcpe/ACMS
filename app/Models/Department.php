<?php

namespace App\Models;

use Database\Factories\DepartmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

#[Fillable(['faculty_id', 'name', 'abbreviation', 'slug', 'minimum_years', 'hod_staff_profile_id'])]
class Department extends Model
{
    /** @use HasFactory<DepartmentFactory> */
    use HasFactory;

    protected $attributes = [
        'minimum_years' => 5,
    ];

    /**
     * @return BelongsTo<Faculty, $this>
     */
    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class);
    }

    /**
     * @return BelongsTo<StaffProfile, $this>
     */
    public function hodStaffProfile(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class, 'hod_staff_profile_id');
    }

    /**
     * @return HasMany<StudentProfile, $this>
     */
    public function studentProfiles(): HasMany
    {
        return $this->hasMany(StudentProfile::class);
    }

    /**
     * @return HasMany<StaffProfile, $this>
     */
    public function staffProfiles(): HasMany
    {
        return $this->hasMany(StaffProfile::class);
    }

    /**
     * @return HasMany<DepartmentLevelStaffAssignment, $this>
     */
    public function levelStaffAssignments(): HasMany
    {
        return $this->hasMany(DepartmentLevelStaffAssignment::class);
    }

    /**
     * @return MorphMany<CourseOffering, $this>
     */
    public function courseOfferings(): MorphMany
    {
        return $this->morphMany(CourseOffering::class, 'owner');
    }

    /**
     * @return Collection<int, Level>
     */
    public function visibleLevels(): Collection
    {
        $minimumLevelIds = range(1, (int) $this->minimum_years);
        $recordLevelIds = $this->levelStaffAssignments()
            ->pluck('level_id')
            ->merge($this->courseOfferings()->pluck('level_id'));

        return Level::query()
            ->whereIn('id', collect($minimumLevelIds)->merge($recordLevelIds)->unique()->values())
            ->orderBy('id')
            ->get();
    }

    public function setMinimumYears(int $years): self
    {
        if ($years < 1 || $years > 7) {
            throw new InvalidArgumentException('Department minimum years must be between 1 and 7.');
        }

        $this->forceFill(['minimum_years' => $years])->save();

        return $this->refresh();
    }

    public function assignHeadOfDepartment(StaffProfile $staffProfile): self
    {
        if (! $staffProfile->exists) {
            throw new InvalidArgumentException('The staff profile must exist before it can be assigned as HOD.');
        }

        if ((int) $staffProfile->department_id !== (int) $this->getKey()) {
            throw new InvalidArgumentException('The HOD must belong to the department they are assigned to.');
        }

        return DB::transaction(function () use ($staffProfile): self {
            $department = self::query()
                ->whereKey($this->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $department->forceFill([
                'hod_staff_profile_id' => $staffProfile->getKey(),
            ])->save();

            return $this->refresh();
        });
    }

    public function assignStaffToLevel(Level $level, StaffProfile $staffProfile): DepartmentLevelStaffAssignment
    {
        if ((int) $level->getKey() < 1 || (int) $level->getKey() > 7) {
            throw new InvalidArgumentException('The level must be one of the canonical levels.');
        }

        if ((int) $staffProfile->department_id !== (int) $this->getKey()) {
            throw new InvalidArgumentException('The staff profile must belong to the department.');
        }

        return $this->levelStaffAssignments()->updateOrCreate(
            ['level_id' => $level->getKey()],
            ['staff_profile_id' => $staffProfile->getKey()],
        );
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'minimum_years' => 'integer',
        ];
    }
}
