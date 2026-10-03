<?php

namespace App\Services;

use App\Grade;
use App\Models\AcademicSessionSemester;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\CourseRegistrationItem;
use App\Models\StudentProfile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ResultService
{
    public function standing(?float $cgpa): ?string
    {
        return match (true) {
            $cgpa === null => null,
            $cgpa >= 4.50 => 'First Class',
            $cgpa >= 3.50 => 'Second Class Upper',
            $cgpa >= 2.40 => 'Second Class Lower',
            $cgpa >= 1.50 => 'Third Class',
            $cgpa >= 1.00 => 'Pass',
            default => 'Below Pass Band',
        };
    }

    /** @return Collection<int, CourseRegistrationItem> */
    public function roster(AcademicSessionSemester $semester, CourseOffering $offering): Collection
    {
        return $this->assignedItems($semester)
            ->where('course_offering_id', $offering->id)
            ->with('courseRegistration.studentProfile.user')
            ->get()
            ->sortBy(fn (CourseRegistrationItem $item): string => Str::lower($item->courseRegistration->studentProfile->official_name), SORT_NATURAL)
            ->values();
    }

    /** @param array<int|string, string|null> $grades */
    public function saveGrades(AcademicSessionSemester $semester, CourseOffering $offering, int $adviserUserId, array $grades, ?string $reason = null): void
    {
        DB::transaction(function () use ($semester, $offering, $adviserUserId, $grades, $reason): void {
            $lockedSemester = AcademicSessionSemester::query()->lockForUpdate()->findOrFail($semester->id);
            $lockedOffering = CourseOffering::query()->lockForUpdate()->findOrFail($offering->id);
            $roster = $this->roster($lockedSemester, $lockedOffering)->keyBy('id');
            $expected = $roster->keys()->map(fn ($id): string => (string) $id)->sort()->values()->all();
            $submitted = collect(array_keys($grades))->map(fn ($id): string => (string) $id)->sort()->values()->all();

            if ($expected === [] || $expected !== $submitted) {
                throw ValidationException::withMessages([
                    'grades' => 'The student list has changed. Reload this course before saving grades.',
                ]);
            }

            foreach ($roster as $item) {
                $result = $grades[$item->id] ?? null;
                $missingScript = $result === 'missing_script';
                $grade = $missingScript ? null : $result;

                if ($lockedOffering->results_published_at !== null && $grade === null && ! $missingScript) {
                    throw ValidationException::withMessages([
                        'grades' => 'Published results cannot be cleared. Choose A to F or Missing script for every student.',
                    ]);
                }

                if ($item->grade === $grade && $item->missing_script === $missingScript) {
                    continue;
                }

                if ($lockedOffering->results_published_at !== null) {
                    if (blank($reason)) {
                        throw ValidationException::withMessages([
                            'reason' => 'Give a reason for changing a published grade.',
                        ]);
                    }

                    DB::table('grade_corrections')->insert([
                        'course_registration_item_id' => $item->id,
                        'changed_by_user_id' => $adviserUserId,
                        'old_grade' => $item->grade,
                        'old_missing_script' => $item->missing_script,
                        'new_grade' => $grade,
                        'new_missing_script' => $missingScript,
                        'reason' => trim($reason),
                        'created_at' => now(),
                    ]);
                }

                $item->forceFill([
                    'grade' => $grade,
                    'missing_script' => $missingScript,
                    'graded_at' => $grade === null ? null : now(),
                ])->save();
            }
        });
    }

    /** @return array{assignedCount: int, totalResults: int, enteredResults: int, canPublish: bool, reason: string|null} */
    public function publicationState(AcademicSessionSemester $semester, CourseOffering $offering): array
    {
        $registrations = CourseRegistration::query()->where('academic_session_semester_id', $semester->id)
            ->whereHas('items', fn (Builder $query) => $query->where('course_offering_id', $offering->id));
        $assignedCount = (clone $registrations)->where('status', CourseRegistration::StatusAssigned)->count();
        $items = $this->assignedItems($semester)->where('course_offering_id', $offering->id);
        $totalResults = (clone $items)->count();
        $enteredResults = (clone $items)->where(fn (Builder $query) => $query
            ->whereNotNull('grade')
            ->orWhere('missing_script', true))->count();
        $reason = match (true) {
            $offering->results_published_at !== null => null,
            $assignedCount === 0 || $totalResults === 0 => 'Assign this course to students before publishing results.',
            $enteredResults < $totalResults => 'Enter the remaining '.($totalResults - $enteredResults).' result(s) before publishing.',
            default => null,
        };

        return compact('assignedCount', 'totalResults', 'enteredResults') + [
            'canPublish' => $offering->results_published_at === null && $reason === null,
            'reason' => $reason,
        ];
    }

    public function publish(AcademicSessionSemester $semester, CourseOffering $offering, int $adviserUserId): void
    {
        DB::transaction(function () use ($semester, $offering, $adviserUserId): void {
            $lockedSemester = AcademicSessionSemester::query()->lockForUpdate()->findOrFail($semester->id);
            $lockedOffering = CourseOffering::query()->lockForUpdate()->findOrFail($offering->id);
            $state = $this->publicationState($lockedSemester, $lockedOffering);

            if (! $state['canPublish']) {
                throw ValidationException::withMessages([
                    'results' => $state['reason'] ?? 'This subject has already been published.',
                ]);
            }

            $lockedOffering->forceFill([
                'results_published_at' => now(),
                'results_published_by_user_id' => $adviserUserId,
            ])->save();
        });
    }

    /** @return Collection<int, CourseRegistration> */
    public function studentRegistrations(StudentProfile $student): Collection
    {
        return $student->courseRegistrations()
            ->where('status', CourseRegistration::StatusAssigned)
            ->with(['items.courseOffering', 'academicSessionSemester.academicSession.academicYear', 'academicSessionSemester.semester'])
            ->get();
    }

    /**
     * @param  Collection<int, int>  $studentIds
     * @return Collection<int, Collection<int, array<string, mixed>>>
     */
    public function summaryRowsForStudents(Collection $studentIds): Collection
    {
        if ($studentIds->isEmpty()) {
            return collect();
        }

        return DB::table('course_registrations as registrations')
            ->join('academic_session_semesters as periods', 'periods.id', '=', 'registrations.academic_session_semester_id')
            ->join('academic_sessions as sessions', 'sessions.id', '=', 'periods.academic_session_id')
            ->join('semesters', 'semesters.id', '=', 'periods.semester_id')
            ->leftJoin('course_registration_items as items', 'items.course_registration_id', '=', 'registrations.id')
            ->leftJoin('course_offerings as offerings', 'offerings.id', '=', 'items.course_offering_id')
            ->whereIn('registrations.student_profile_id', $studentIds)
            ->where('registrations.status', CourseRegistration::StatusAssigned)
            ->orderBy('registrations.student_profile_id')
            ->orderBy('sessions.start_year')
            ->orderBy('semesters.sort_order')
            ->orderBy('items.id')
            ->get([
                'registrations.id as registration_id',
                'registrations.student_profile_id',
                'periods.id as period_id',
                'sessions.start_year',
                'semesters.sort_order as semester_order',
                'items.id as item_id',
                'items.course_offering_id',
                'items.assignment_origin',
                'items.credit_units',
                'items.grade',
                'items.missing_script',
                'offerings.results_published_at',
            ])
            ->groupBy('student_profile_id')
            ->map(function (Collection $studentRows): Collection {
                $running = ['points' => 0, 'units' => 0, 'incomplete' => false];

                return $studentRows->groupBy('registration_id')->map(function (Collection $periodRows) use (&$running): array {
                    $period = $periodRows->first();
                    $items = $periodRows->whereNotNull('item_id')->values();
                    $grades = $items->map(fn (object $item): array => [
                        'grade' => $item->grade,
                        'units' => (int) $item->credit_units,
                        'published' => $item->results_published_at !== null,
                    ]);

                    return [
                        'periodId' => (int) $period->period_id,
                        'startYear' => (int) $period->start_year,
                        'semesterOrder' => (int) $period->semester_order,
                        'items' => $items,
                        ...$this->periodMetrics($grades, $running),
                    ];
                })->values();
            });
    }

    /**
     * @param  Collection<int, CourseRegistration>  $registrations
     * @return Collection<int, array{registration: CourseRegistration, visibleItems: Collection, counted: bool, gpa: ?float, cgpa: ?float}>
     */
    public function studentPeriods(Collection $registrations): Collection
    {
        $running = ['points' => 0, 'units' => 0, 'incomplete' => false];

        return $registrations->sortBy(fn (CourseRegistration $registration): string => sprintf(
            '%04d-%02d',
            $registration->academicSessionSemester->academicSession->start_year,
            $registration->academicSessionSemester->semester->sort_order,
        ))->values()->map(function (CourseRegistration $registration) use (&$running): array {
            $items = $registration->items;
            $visibleItems = $items->filter(fn (CourseRegistrationItem $item): bool => $item->courseOffering?->results_published_at !== null
                && ($item->grade !== null || $item->missing_script))->values();
            $grades = $items->map(fn (CourseRegistrationItem $item): array => [
                'grade' => $item->grade,
                'units' => (int) $item->credit_units,
                'published' => $item->courseOffering?->results_published_at !== null,
            ]);

            return [
                'registration' => $registration,
                'visibleItems' => $visibleItems,
                ...$this->periodMetrics($grades, $running),
            ];
        });
    }

    /**
     * @param  Collection<int, array{grade: ?string, units: int, published: bool}>  $grades
     * @param  array{points: int, units: int, incomplete: bool}  $running
     * @return array{counted: bool, gpa: ?float, cgpa: ?float}
     */
    private function periodMetrics(Collection $grades, array &$running): array
    {
        $complete = $grades->isNotEmpty();
        $points = 0;
        $units = 0;

        foreach ($grades as $item) {
            $units += $item['units'];
            if ($item['grade'] === null || ! $item['published']) {
                $complete = false;
            }
            if ($item['grade'] !== null) {
                $points += Grade::from($item['grade'])->points() * $item['units'];
            }
        }

        $counted = $complete && ! $running['incomplete'];
        if (! $complete) {
            $running['incomplete'] = true;
        } elseif ($counted) {
            $running['points'] += $points;
            $running['units'] += $units;
        }

        return [
            'counted' => $counted,
            'gpa' => $complete && $units > 0 ? round($points / $units, 2) : null,
            'cgpa' => $running['units'] > 0 ? round($running['points'] / $running['units'], 2) : null,
        ];
    }

    /** @param Collection<int, CourseRegistrationItem> $items */
    public function gpa(Collection $items): ?float
    {
        if ($items->isEmpty() || $items->contains(fn (CourseRegistrationItem $item): bool => $item->grade === null)) {
            return null;
        }

        $units = (int) $items->sum('credit_units');

        if ($units === 0) {
            return null;
        }

        $weightedPoints = $items->sum(fn (CourseRegistrationItem $item): int => Grade::from($item->grade)->points() * (int) $item->credit_units);

        return round($weightedPoints / $units, 2);
    }

    /** @return Builder<CourseRegistrationItem> */
    private function assignedItems(AcademicSessionSemester $semester): Builder
    {
        return CourseRegistrationItem::query()
            ->whereHas('courseRegistration', fn (Builder $query) => $query
                ->where('academic_session_semester_id', $semester->id)
                ->where('status', CourseRegistration::StatusAssigned)
                ->whereHas('studentProfile', fn (Builder $students) => $students
                    ->where('student_set_id', $semester->academicSession->student_set_id)));
    }
}
