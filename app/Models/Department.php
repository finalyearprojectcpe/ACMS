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

#[Fillable(['faculty_id', 'name', 'abbreviation', 'slug', 'minimum_years_to_degree', 'hod_staff_profile_id'])]
class Department extends Model
{
    /** @use HasFactory<DepartmentFactory> */
    use HasFactory;

    protected $attributes = [
        'minimum_years_to_degree' => 5,
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
     * @return HasMany<StudentSetAdvisorAssignment, $this>
     */
    public function studentSetAdvisorAssignments(): HasMany
    {
        return $this->hasMany(StudentSetAdvisorAssignment::class);
    }

    /**
     * @return MorphMany<CourseOffering, $this>
     */
    public function courseOfferings(): MorphMany
    {
        return $this->morphMany(CourseOffering::class, 'owner');
    }

    /**
     * @return HasMany<ResultSheet, $this>
     */
    public function resultSheets(): HasMany
    {
        return $this->hasMany(ResultSheet::class);
    }

    /**
     * @return HasMany<Due, $this>
     */
    public function dues(): HasMany
    {
        return $this->hasMany(Due::class);
    }

    /**
     * @return HasMany<PaymentAccount, $this>
     */
    public function paymentAccounts(): HasMany
    {
        return $this->hasMany(PaymentAccount::class);
    }

    /**
     * @return Collection<int, AcademicYear>
     */
    public function visibleAcademicYears(): Collection
    {
        $minimumAcademicYearIds = range(1, (int) $this->minimum_years_to_degree);
        $recordAcademicYearIds = $this->studentProfiles()
            ->pluck('current_academic_year_id')
            ->merge(
                $this->courseOfferings()
                    ->with('academicYearSemester')
                    ->get()
                    ->pluck('academicYearSemester.academic_year_id')
                    ->filter()
            );

        return AcademicYear::query()
            ->whereIn('id', collect($minimumAcademicYearIds)->merge($recordAcademicYearIds)->unique()->values())
            ->orderBy('id')
            ->get();
    }

    public function setMinimumYearsToDegree(int $years): self
    {
        if ($years < 1 || $years > 7) {
            throw new InvalidArgumentException('Department minimum years to degree must be between 1 and 7.');
        }

        $this->forceFill(['minimum_years_to_degree' => $years])->save();

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

    public function assignAdvisorToStudentSet(
        AcademicSession $entryAcademicSession,
        StaffProfile $staffProfile,
        ?AcademicSession $startsAcademicSession = null,
        ?StaffProfile $assignedByStaffProfile = null,
    ): StudentSetAdvisorAssignment {
        $schoolId = (int) $this->faculty()->value('school_id');

        if ((int) $entryAcademicSession->school_id !== $schoolId) {
            throw new InvalidArgumentException('The entry academic session must belong to the department school.');
        }

        if ($startsAcademicSession !== null && (int) $startsAcademicSession->school_id !== $schoolId) {
            throw new InvalidArgumentException('The start academic session must belong to the department school.');
        }

        if ($startsAcademicSession === null) {
            $startsAcademicSession = AcademicSession::query()
                ->where('school_id', $schoolId)
                ->whereDate('starts_on', '<=', today())
                ->whereDate('ends_on', '>=', today())
                ->orderByDesc('starts_on')
                ->first();
        }

        if ((int) $staffProfile->department_id !== (int) $this->getKey()) {
            throw new InvalidArgumentException('The course advisor must belong to the department.');
        }

        if ($assignedByStaffProfile !== null && (int) $assignedByStaffProfile->department_id !== (int) $this->getKey()) {
            throw new InvalidArgumentException('The assigning staff profile must belong to the department.');
        }

        return DB::transaction(function () use ($entryAcademicSession, $staffProfile, $startsAcademicSession, $assignedByStaffProfile): StudentSetAdvisorAssignment {
            $department = self::query()
                ->whereKey($this->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $activeAssignment = $department->studentSetAdvisorAssignments()
                ->where('entry_academic_session_id', $entryAcademicSession->getKey())
                ->where('active_marker', 1)
                ->lockForUpdate()
                ->first();

            if (
                $activeAssignment instanceof StudentSetAdvisorAssignment
                && (int) $activeAssignment->staff_profile_id === (int) $staffProfile->getKey()
            ) {
                $activeAssignment->forceFill([
                    'assigned_by_staff_profile_id' => $assignedByStaffProfile?->getKey(),
                    'starts_academic_session_id' => $startsAcademicSession?->getKey(),
                    'ends_academic_session_id' => null,
                    'active_marker' => 1,
                ])->save();

                return $activeAssignment->refresh();
            }

            if ($activeAssignment instanceof StudentSetAdvisorAssignment) {
                $activeAssignment->forceFill([
                    'ends_academic_session_id' => $startsAcademicSession?->getKey(),
                    'active_marker' => null,
                ])->save();
            }

            return $department->studentSetAdvisorAssignments()->create([
                'entry_academic_session_id' => $entryAcademicSession->getKey(),
                'staff_profile_id' => $staffProfile->getKey(),
                'assigned_by_staff_profile_id' => $assignedByStaffProfile?->getKey(),
                'starts_academic_session_id' => $startsAcademicSession?->getKey(),
                'ends_academic_session_id' => null,
                'active_marker' => 1,
            ]);
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'minimum_years_to_degree' => 'integer',
        ];
    }
}
