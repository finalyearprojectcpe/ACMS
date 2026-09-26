<?php

namespace App\Services;

use App\Honorific;
use App\Models\AcademicSession;
use App\Models\AcademicYear;
use App\Models\AdvisorProfile;
use App\Models\StudentProfile;
use App\Models\StudentSet;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class DashboardPresenter
{
    public function __construct(
        private readonly AcademicContextService $academicContext,
        private readonly CurrencyFormatter $currency,
    ) {}

    public function adviserStudents(
        AdvisorProfile $advisor,
        Collection $sets,
        ?int $selectedSetId = null,
    ): array {
        $selectedSet = $sets->firstWhere('id', $selectedSetId) ?? $sets->first();
        $students = $advisor->students()
            ->with(['user', 'studentSet.currentAcademicYear'])
            ->when(
                $selectedSet instanceof StudentSet,
                fn ($query) => $query->where('student_set_id', $selectedSet->id),
                fn ($query) => $query->whereRaw('1 = 0'),
            )
            ->get()
            ->sortBy(
                fn (StudentProfile $student): string => Str::lower($student->user->name),
                SORT_NATURAL,
            )
            ->values();

        return [
            'assignedSets' => $sets->map(fn (StudentSet $set): array => $this->studentSet($set))->values()->all(),
            'selectedSetId' => $selectedSet instanceof StudentSet ? (int) $selectedSet->id : null,
            'totalStudents' => $students->count(),
            'students' => $students->map(fn (StudentProfile $student): array => $this->adviserStudent($student))->all(),
        ];
    }

    public function adviserStudent(StudentProfile $student): array
    {
        $student->loadMissing([
            'user',
            'studentSet.currentAcademicYear',
            'advisorProfile.user',
        ]);

        return [
            'id' => (int) $student->id,
            'fullName' => $student->user->name,
            'email' => $student->user->email,
            'registrationNumber' => $student->registration_number,
            'avatar' => $student->user->profilePhotoUrl(),
            'currentLevel' => $student->studentSet->currentAcademicYear instanceof AcademicYear
                ? $this->level($student->studentSet->currentAcademicYear)
                : null,
            'studentSet' => $this->studentSet($student->studentSet),
            'entrySession' => $this->entrySession($student->studentSet),
            'advisorName' => $student->advisorProfile->user->displayName(),
            'profileUrl' => route('dashboard.students.show', [
                'registrationNumber' => $student->registration_number,
            ]),
        ];
    }

    public function adviserStudentSets(bool $isCreating = false): array
    {
        return [
            'isCreating' => $isCreating,
        ];
    }

    public function adviserProfile(AdvisorProfile $advisor): array
    {
        return [
            'adviser' => [
                'title' => $advisor->user->title?->value,
                'name' => $advisor->user->name,
                'displayName' => $advisor->user->displayName(),
                'email' => $advisor->user->email,
                'avatar' => $advisor->user->profilePhotoUrl(),
            ],
            'honorifics' => Honorific::options(),
        ];
    }

    public function studentProfile(StudentProfile $student): array
    {
        [$surname, $otherNames] = $this->splitName($student->user->name);

        return [
            'name' => [
                'full' => $student->user->name,
                'surname' => $surname,
                'otherNames' => $otherNames,
            ],
            'avatar' => $student->user->profilePhotoUrl(),
        ];
    }

    public function studentRecord(StudentProfile $student): array
    {
        return [
            'student' => $this->adviserStudent($student),
            'entrySession' => $this->entrySession($student->studentSet),
            'courseAdvisor' => $student->advisorProfile->user->displayName(),
        ];
    }

    public function studentHeader(StudentProfile $student): array
    {
        return [
            'registrationNumber' => $student->registration_number,
            'currentLevel' => $student->studentSet->currentAcademicYear instanceof AcademicYear
                ? $this->level($student->studentSet->currentAcademicYear)
                : null,
        ];
    }

    public function studentSet(StudentSet $set): array
    {
        $set->loadMissing('advisorProfile.user');
        if (! isset($set->students_count)) {
            $set->loadCount('students');
        }
        $currentLevel = $this->academicContext->academicYearForSet($set);

        return [
            'id' => (int) $set->id,
            'publicId' => $set->public_id,
            'name' => $set->label(),
            'entrySession' => $this->entrySession($set),
            'currentLevel' => $currentLevel instanceof AcademicYear ? $this->level($currentLevel) : null,
            'advisorName' => $set->advisorProfile->user->displayName(),
            'studentCount' => (int) $set->students_count,
            'signupUrl' => $set->signupUrl(),
            'signupEnabled' => (bool) $set->student_signup_enabled,
            'url' => route('student-sets.show', $set->routeParameters()),
        ];
    }

    public function academicSession(AcademicSession $session): array
    {
        return [
            'id' => (int) $session->id,
            'publicId' => $session->public_id,
            'name' => $session->label(),
            'departmentName' => $session->studentSet->department_name,
            'departmentSlug' => $session->studentSet->department_slug,
            'startYear' => (int) $session->start_year,
            'endYear' => (int) $session->end_year,
        ];
    }

    public function entrySession(StudentSet $set): array
    {
        return [
            'id' => $set->id,
            'publicId' => $set->public_id,
            'name' => "{$set->entry_start_year}/{$set->entry_end_year}",
            'departmentName' => $set->department_name,
            'departmentSlug' => $set->department_slug,
            'startYear' => (int) $set->entry_start_year,
            'endYear' => (int) $set->entry_end_year,
        ];
    }

    public function level(AcademicYear $level): array
    {
        return [
            'id' => (int) $level->id,
            'title' => $level->name,
            'slug' => $level->slug,
            'yearNumber' => (int) $level->year_number,
        ];
    }

    public function amount(int $amountKobo): string
    {
        return $this->currency->nairaFromKobo($amountKobo);
    }

    /** @return array{0: string, 1: string} */
    private function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name), 2);

        return [$parts[0] ?? '', $parts[1] ?? ''];
    }
}
