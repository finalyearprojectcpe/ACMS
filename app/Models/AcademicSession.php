<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\AcademicSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['advisor_profile_id', 'name', 'starts_on', 'ends_on'])]
class AcademicSession extends Model
{
    /** @use HasFactory<AcademicSessionFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::created(fn (AcademicSession $academicSession) => $academicSession->syncDefaultSemesterInstances());
        static::updated(function (AcademicSession $academicSession): void {
            if ($academicSession->wasChanged(['starts_on', 'ends_on'])) {
                $academicSession->syncDefaultSemesterInstances();
            }
        });
    }

    /**
     * @return BelongsTo<AdvisorProfile, $this>
     */
    public function advisorProfile(): BelongsTo
    {
        return $this->belongsTo(AdvisorProfile::class);
    }

    /**
     * @return HasMany<AcademicSessionSemester, $this>
     */
    public function semesterInstances(): HasMany
    {
        return $this->hasMany(AcademicSessionSemester::class);
    }

    public function syncDefaultSemesterInstances(): void
    {
        $semesters = Semester::query()
            ->whereIn('slug', ['harmattan', 'rain'])
            ->get()
            ->keyBy('slug');

        if ($semesters->count() !== 2 || $this->starts_on === null || $this->ends_on === null) {
            return;
        }

        $startsOn = CarbonImmutable::parse($this->starts_on);
        $endsOn = CarbonImmutable::parse($this->ends_on);
        $rainStartsOn = $startsOn->addDays((int) ceil($startsOn->diffInDays($endsOn) / 2));
        $periods = [
            'harmattan' => [$startsOn, $rainStartsOn->subDay()],
            'rain' => [$rainStartsOn, $endsOn],
        ];

        foreach ($periods as $semesterSlug => [$semesterStartsOn, $semesterEndsOn]) {
            $this->semesterInstances()->updateOrCreate(
                ['semester_id' => $semesters->get($semesterSlug)->id],
                [
                    'starts_on' => $semesterStartsOn->toDateString(),
                    'ends_on' => $semesterEndsOn->toDateString(),
                    'registration_opens_at' => $semesterStartsOn->subWeeks(2),
                    'registration_closes_at' => $semesterStartsOn->addWeeks(4)->endOfDay(),
                ],
            );
        }
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }
}
