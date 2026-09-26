<?php

namespace App\Services;

use App\Grade;
use App\Models\AcademicSessionSemester;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\CourseRegistrationItem;
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
    public function saveGrades(AcademicSessionSemester $semester, CourseOffering $offering, int $adviserUserId, array $grades): void
    {
        DB::transaction(function () use ($semester, $offering, $adviserUserId, $grades): void {
            $lockedSemester = AcademicSessionSemester::query()->lockForUpdate()->findOrFail($semester->id);
            $roster = $this->roster($lockedSemester, $offering)->keyBy('id');
            $expected = $roster->keys()->map(fn ($id): string => (string) $id)->sort()->values()->all();
            $submitted = collect(array_keys($grades))->map(fn ($id): string => (string) $id)->sort()->values()->all();

            if ($expected === [] || $expected !== $submitted) {
                throw ValidationException::withMessages([
                    'grades' => 'The student list has changed. Reload this course before saving grades.',
                ]);
            }

            foreach ($roster as $item) {
                $grade = $grades[$item->id] ?? null;

                if ($lockedSemester->results_published_at !== null && $grade === null) {
                    throw ValidationException::withMessages([
                        'grades' => 'Published grades cannot be cleared. Choose A to F for every student.',
                    ]);
                }

                if ($item->grade === $grade) {
                    continue;
                }

                if ($lockedSemester->results_published_at !== null) {
                    DB::table('grade_corrections')->insert([
                        'course_registration_item_id' => $item->id,
                        'changed_by_user_id' => $adviserUserId,
                        'old_grade' => $item->grade,
                        'new_grade' => $grade,
                        'created_at' => now(),
                    ]);
                }

                $item->forceFill(['grade' => $grade, 'graded_at' => $grade === null ? null : now()])->save();
            }
        });
    }

    /** @return array{approvedCount: int, awaitingReviewCount: int, totalGrades: int, enteredGrades: int, canPublish: bool, reason: string|null} */
    public function publicationState(AcademicSessionSemester $semester): array
    {
        $registrations = CourseRegistration::query()->where('academic_session_semester_id', $semester->id);
        $approvedCount = (clone $registrations)->where('status', CourseRegistration::StatusApproved)->count();
        $awaitingReviewCount = (clone $registrations)->where('status', CourseRegistration::StatusSubmitted)->count();
        $items = $this->approvedItems($semester);
        $totalGrades = (clone $items)->count();
        $enteredGrades = (clone $items)->whereNotNull('grade')->count();
        $reason = match (true) {
            $semester->results_published_at !== null => null,
            $semester->registration_enabled => 'Close course registration before publishing results.',
            $awaitingReviewCount > 0 => 'Review '.$awaitingReviewCount.' submitted '.($awaitingReviewCount === 1 ? 'registration' : 'registrations').' before publishing.',
            $approvedCount === 0 || $totalGrades === 0 => 'No approved course registrations are ready for results.',
            $enteredGrades < $totalGrades => 'Enter the remaining '.($totalGrades - $enteredGrades).' grade(s) before publishing.',
            default => null,
        };

        return compact('approvedCount', 'awaitingReviewCount', 'totalGrades', 'enteredGrades') + [
            'canPublish' => $semester->results_published_at === null && $reason === null,
            'reason' => $reason,
        ];
    }

    public function publish(AcademicSessionSemester $semester): void
    {
        DB::transaction(function () use ($semester): void {
            $lockedSemester = AcademicSessionSemester::query()->lockForUpdate()->findOrFail($semester->id);
            $state = $this->publicationState($lockedSemester);

            if (! $state['canPublish']) {
                throw ValidationException::withMessages([
                    'results' => $state['reason'] ?? 'These semester results have already been published.',
                ]);
            }

            $lockedSemester->forceFill(['results_published_at' => now()])->save();
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
