<?php

namespace App\Services;

use App\Models\AdvisorProfile;
use App\Models\DuePayment;
use App\Models\StudentProfile;
use App\Models\StudentSet;
use Illuminate\Http\Request;

class AdviserActivityData
{
    public function __construct(
        private readonly CurrencyFormatter $currency,
        private readonly AdviserJoinRequestData $joins,
    ) {}

    public function home(AdvisorProfile $advisor): array
    {
        $payments = $this->paymentQuery($advisor)->latest('paid_at');
        $unassigned = $advisor->studentSets()->with(['currentSemester', 'academicSessions.semesterInstances.semester'])->get()
            ->flatMap(function (StudentSet $set): array {
                $session = $set->academicSessions->firstWhere('academic_year_id', $set->current_academic_year_id);
                $period = $session?->semesterInstances->firstWhere('semester_id', $set->current_semester_id);

                if ($period === null) {
                    return [];
                }

                return $set->students()->whereDoesntHave('courseRegistrations', fn ($query) => $query
                    ->where('academic_session_semester_id', $period->id)
                    ->where('status', 'assigned'))
                    ->with('user')->orderBy('official_name')->get()->map(fn (StudentProfile $student): array => [
                        'name' => $student->official_name,
                        'avatar' => $student->user?->profilePhotoUrl(),
                        'registrationNumber' => $student->registration_number,
                        'set' => $set->label(),
                        'period' => $session->academicYear->name.' '.$period->semester->name,
                        'profileUrl' => route('dashboard.students.show', ['registrationNumber' => $student->routeRegistrationNumber()]),
                        'url' => route('student-sets.assignments.show', [
                            ...$set->routeParameters(),
                            'sessionYears' => $session->routeYears(),
                            'semester' => $period->semester->slug,
                            'registrationNumber' => $student->routeRegistrationNumber(),
                            'from' => 'home',
                        ]),
                    ])->all();
            });

        return [
            'hasSets' => $advisor->studentSets()->exists(),
            ...$this->joins->home($advisor),
            'unassignedCount' => $unassigned->count(),
            'unassignedStudents' => $unassigned->take(5)->values()->all(),
            'paymentCount' => (clone $payments)->count(),
            'recentPayments' => $payments->limit(5)->get()->map($this->paymentRow(...))->all(),
        ];
    }

    public function payments(AdvisorProfile $advisor, Request $request): array
    {
        $sets = $advisor->studentSets()->orderByDesc('entry_start_year')->get();
        $selectedSet = $sets->first(fn ($set): bool => $set->filterKey() === $request->query('set'));
        abort_if($request->query('set') !== null && $selectedSet === null, 404);
        $from = $request->date('from')?->toDateString();
        $to = $request->date('to')?->toDateString();
        $rows = $this->paymentQuery($advisor)
            ->when($selectedSet, fn ($query) => $query->whereHas('studentProfile', fn ($students) => $students->where('student_set_id', $selectedSet->id)))
            ->when($from, fn ($query) => $query->whereDate('paid_at', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('paid_at', '<=', $to))
            ->orderByDesc('paid_at')->orderByDesc('id')
            ->paginate(15)->withQueryString();

        return [
            'sets' => $sets->map(fn ($set): array => ['id' => $set->filterKey(), 'name' => $set->label()])->all(),
            'filters' => ['set' => $selectedSet?->filterKey(), 'from' => $from, 'to' => $to],
            'payments' => $rows->through($this->paymentRow(...)),
        ];
    }

    private function paymentQuery(AdvisorProfile $advisor)
    {
        return DuePayment::query()
            ->with(['studentProfile.user', 'studentProfile.studentSet', 'assignment.due'])
            ->where('advisor_profile_id', $advisor->id)
            ->where('status', DuePayment::StatusSuccess);
    }

    private function paymentRow(DuePayment $payment): array
    {
        $student = $payment->studentProfile;

        return [
            'id' => (int) $payment->id,
            'studentName' => $student->user->name,
            'studentAvatar' => $student->user->profilePhotoUrl(),
            'studentUrl' => route('dashboard.students.show', ['registrationNumber' => $student->routeRegistrationNumber()]),
            'set' => $student->studentSet->label(),
            'dueName' => $payment->assignment->due->title,
            'dueUrl' => route('dashboard.dues.show', ['due' => $payment->assignment->due]),
            'amount' => $this->currency->nairaFromKobo((int) $payment->amount_kobo),
            'paidAt' => $payment->paid_at?->toFormattedDateString(),
            'receiptUrl' => $payment->paystack_transaction_id
                ? route('dashboard.dues.payments.receipt', ['paystackTransactionId' => $payment->paystack_transaction_id])
                : null,
        ];
    }
}
