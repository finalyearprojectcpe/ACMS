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
    /** @return Collection<int, CourseRegistrationItem> */
    public function roster(AcademicSessionSemester $semester, CourseOffering $offering): Collection
    {
        return $this->approvedItems($semester)
            ->where('course_offering_id', $offering->id)
            ->with('courseRegistration.studentProfile.user')
            ->get()
            ->sortBy(fn (CourseRegistrationItem $item): string => Str::lower($item->courseRegistration->studentProfile->user->name), SORT_NATURAL)
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

    /** @return array{approvedCount: int, awaitingReviewCount: int, totalResults: int, enteredResults: int, canPublish: bool, reason: string|null} */
    public function publicationState(AcademicSessionSemester $semester, CourseOffering $offering): array
    {
        $registrations = CourseRegistration::query()->where('academic_session_semester_id', $semester->id)
            ->whereHas('items', fn (Builder $query) => $query->where('course_offering_id', $offering->id));
        $approvedCount = (clone $registrations)->where('status', CourseRegistration::StatusApproved)->count();
        $awaitingReviewCount = (clone $registrations)->where('status', CourseRegistration::StatusSubmitted)->count();
        $items = $this->approvedItems($semester)->where('course_offering_id', $offering->id);
        $totalResults = (clone $items)->count();
        $enteredResults = (clone $items)->where(fn (Builder $query) => $query
            ->whereNotNull('grade')
            ->orWhere('missing_script', true))->count();
        $reason = match (true) {
            $offering->results_published_at !== null => null,
            $semester->registration_enabled => 'Close course registration before publishing results.',
            $awaitingReviewCount > 0 => 'Review '.$awaitingReviewCount.' submitted '.($awaitingReviewCount === 1 ? 'registration' : 'registrations').' before publishing.',
            $approvedCount === 0 || $totalResults === 0 => 'No approved course registrations are ready for results.',
            $enteredResults < $totalResults => 'Enter the remaining '.($totalResults - $enteredResults).' result(s) before publishing.',
            default => null,
        };

        return compact('approvedCount', 'awaitingReviewCount', 'totalResults', 'enteredResults') + [
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
            ->where('status', CourseRegistration::StatusApproved)
            ->with(['items.courseOffering', 'academicSessionSemester.academicSession.academicYear', 'academicSessionSemester.semester'])
            ->get();
    }

    /**
     * @param  Collection<int, CourseRegistration>  $registrations
     * @return Collection<int, array{registration: CourseRegistration, visibleItems: Collection, counted: bool, gpa: ?float, cgpa: ?float}>
     */
    public function studentPeriods(Collection $registrations): Collection
    {
        $cumulativeItems = collect();
        $hasIncompletePeriod = false;

        return $registrations->sortBy(fn (CourseRegistration $registration): string => sprintf(
            '%04d-%02d',
            $registration->academicSessionSemester->academicSession->start_year,
            $registration->academicSessionSemester->semester->sort_order,
        ))->values()->map(function (CourseRegistration $registration) use ($cumulativeItems, &$hasIncompletePeriod): array {
            $items = $registration->items;
            $visibleItems = $items->filter(fn (CourseRegistrationItem $item): bool => $item->courseOffering?->results_published_at !== null
                && ($item->grade !== null || $item->missing_script))->values();
            $complete = $items->isNotEmpty() && $visibleItems->count() === $items->count()
                && ! $items->contains(fn (CourseRegistrationItem $item): bool => $item->grade === null);

            $counted = $complete && ! $hasIncompletePeriod;
            if (! $complete) {
                $hasIncompletePeriod = true;
            } elseif ($counted) {
                foreach ($items as $item) {
                    $cumulativeItems->push($item);
                }
            }

            return [
                'registration' => $registration,
                'visibleItems' => $visibleItems,
                'counted' => $counted,
                'gpa' => $complete ? $this->gpa($items) : null,
                'cgpa' => $this->gpa($cumulativeItems),
            ];
        });
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
    private function approvedItems(AcademicSessionSemester $semester): Builder
    {
        return CourseRegistrationItem::query()
            ->whereHas('courseRegistration', fn (Builder $query) => $query
                ->where('academic_session_semester_id', $semester->id)
                ->where('status', CourseRegistration::StatusApproved)
                ->whereHas('studentProfile', fn (Builder $students) => $students
                    ->where('student_set_id', $semester->academicSession->student_set_id)));
    }
}
