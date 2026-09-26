<?php

namespace App\Services;

use App\Models\AdvisorProfile;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\Due;
use App\Models\DuePayment;
use App\Models\StudentProfile;
use App\Models\StudentSet;

class AssistantRecordSearch
{
    public function __construct(private readonly CurrencyFormatter $currency) {}

    public function search(AdvisorProfile $advisor, string $category, string $term, string $status = 'any'): array
    {
        $term = trim(mb_substr($term, 0, 80));
        $like = '%'.$term.'%';

        return match ($category) {
            'students' => $this->students($advisor, $like),
            'sets' => $this->sets($advisor, $term),
            'registrations' => $this->registrations($advisor, $like, $status),
            'courses' => $this->courses($advisor, $like),
            'dues' => $this->dues($advisor, $like),
            'payments' => $this->payments($advisor, $like),
            default => ['count' => 0, 'records' => []],
        };
    }

    private function sets(AdvisorProfile $advisor, string $term): array
    {
        $sets = $advisor->studentSets()->withCount('students')->orderByDesc('entry_start_year')->get()
            ->filter(fn (StudentSet $set): bool => $term === '' || str_contains(mb_strtolower($set->label()), mb_strtolower($term)));

        return $this->result($sets->count(), $sets->take(20)->map(fn (StudentSet $set): array => [
            'title' => $set->label(),
            'detail' => $set->students_count.' students',
            'url' => route('student-sets.show', $set->routeParameters()),
        ])->values()->all());
    }

    private function students(AdvisorProfile $advisor, string $like): array
    {
        $query = StudentProfile::query()->where('advisor_profile_id', $advisor->id)
            ->where(fn ($query) => $query->where('registration_number', 'like', $like)
                ->orWhereHas('user', fn ($users) => $users->where('name', 'like', $like)));

        return $this->result($query->count(), $query->with(['user', 'studentSet'])->orderBy('registration_number')->limit(20)->get()
            ->map(fn (StudentProfile $student): array => [
                'title' => $student->user->name,
                'detail' => $student->registration_number.' · '.$student->studentSet->label(),
                'url' => route('dashboard.students.show', ['registrationNumber' => $student->registration_number]),
            ])->all());
    }

    private function registrations(AdvisorProfile $advisor, string $like, string $status): array
    {
        $query = CourseRegistration::query()
            ->where('status', '!=', CourseRegistration::StatusDraft)
            ->when(in_array($status, [CourseRegistration::StatusSubmitted, CourseRegistration::StatusApproved, CourseRegistration::StatusReturned], true), fn ($query) => $query->where('status', $status))
            ->whereHas('studentProfile', fn ($students) => $students->where('advisor_profile_id', $advisor->id))
            ->whereHas('studentProfile', fn ($students) => $students->where('registration_number', 'like', $like)
                ->orWhereHas('user', fn ($users) => $users->where('name', 'like', $like)));

        return $this->result($query->count(), $query->with(['studentProfile.user', 'studentProfile.studentSet', 'academicSessionSemester.academicSession', 'academicSessionSemester.semester'])
            ->latest('submitted_at')->limit(20)->get()->map(fn (CourseRegistration $item): array => [
                'title' => $item->studentProfile->user->name.' · '.$item->academicSessionSemester->semester->name,
                'detail' => $item->studentProfile->studentSet->label().' · '.$item->academicSessionSemester->academicSession->label().' Session · '.$item->status,
                'url' => route('dashboard.course-registration.show', $item->reviewRouteParameters()),
            ])->all());
    }

    private function courses(AdvisorProfile $advisor, string $like): array
    {
        $query = CourseOffering::query()
            ->whereHas('course.studentSet', fn ($sets) => $sets->where('advisor_profile_id', $advisor->id))
            ->where(fn ($offerings) => $offerings->where('course_title', 'like', $like)
                ->orWhereHas('course', fn ($courses) => $courses->where('code', 'like', $like)));

        return $this->result($query->count(), $query->with(['course', 'academicSessionSemester.academicSession.studentSet', 'academicSessionSemester.semester'])
            ->latest('id')->limit(20)->get()->map(function (CourseOffering $item): array {
                $session = $item->academicSessionSemester->academicSession;

                return [
                    'title' => $item->course->code.' '.$item->course_title,
                    'detail' => $session->studentSet->label().' · '.$session->label().' Session · '.$item->academicSessionSemester->semester->name.' · '.$item->credit_units.' units'.($item->is_elective ? ' · Elective' : ''),
                    'url' => route('student-sets.courses.show', [
                        ...$session->studentSet->routeParameters(),
                        'session' => $session->routeYears(),
                        'semester' => $item->academicSessionSemester->semester->slug,
                    ]),
                ];
            })->all());
    }

    private function dues(AdvisorProfile $advisor, string $like): array
    {
        $query = Due::query()->where('advisor_profile_id', $advisor->id)->where('title', 'like', $like);

        return $this->result($query->count(), $query->with('studentSet')->latest('id')->limit(20)->get()
            ->map(fn (Due $due): array => [
                'title' => $due->title,
                'detail' => $due->studentSet->label().' · '.$this->currency->nairaFromKobo((int) $due->amount_kobo).' · '.$due->status,
                'url' => route('dashboard.dues.show', ['due' => $due]),
            ])->all());
    }

    private function payments(AdvisorProfile $advisor, string $like): array
    {
        $query = DuePayment::query()->where('advisor_profile_id', $advisor->id)
            ->where('status', DuePayment::StatusSuccess)
            ->where(fn ($payments) => $payments->whereHas('studentProfile.user', fn ($users) => $users->where('name', 'like', $like))
                ->orWhereHas('assignment.due', fn ($dues) => $dues->where('title', 'like', $like)));

        return $this->result($query->count(), $query->with(['studentProfile.user', 'assignment.due'])
            ->latest('paid_at')->limit(20)->get()->map(fn (DuePayment $payment): array => [
                'title' => $payment->studentProfile->user->name.' · '.$payment->assignment->due->title,
                'detail' => $this->currency->nairaFromKobo((int) $payment->amount_kobo).' · '.$payment->paid_at?->toDateString(),
                'url' => route('dashboard.dues.show', ['due' => $payment->assignment->due]),
            ])->all());
    }

    private function result(int $count, array $records): array
    {
        return ['count' => $count, 'records' => $records, 'moreAvailable' => $count > count($records)];
    }
}
