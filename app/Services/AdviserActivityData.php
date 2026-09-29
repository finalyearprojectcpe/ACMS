<?php

namespace App\Services;

use App\Models\AdvisorProfile;
use App\Models\CourseRegistration;
use App\Models\DuePayment;
use Illuminate\Http\Request;

class AdviserActivityData
{
    public function __construct(
        private readonly CurrencyFormatter $currency,
        private readonly AdviserJoinRequestData $joins,
    ) {}

    public function home(AdvisorProfile $advisor): array
    {
        $registrations = $this->registrationQuery($advisor)
            ->where('status', CourseRegistration::StatusSubmitted)
            ->latest('submitted_at');
        $payments = $this->paymentQuery($advisor)->latest('paid_at');

        return [
            'hasSets' => $advisor->studentSets()->exists(),
            ...$this->joins->home($advisor),
            'pendingCount' => (clone $registrations)->count(),
            'pendingRegistrations' => $registrations->limit(5)->get()->map($this->registrationRow(...))->all(),
            'paymentCount' => (clone $payments)->count(),
            'recentPayments' => $payments->limit(5)->get()->map($this->paymentRow(...))->all(),
        ];
    }

    public function registrations(AdvisorProfile $advisor, Request $request): array
    {
        $status = $request->query('status', 'submitted');
        $status = in_array($status, ['submitted', 'approved', 'returned', 'all'], true) ? $status : 'submitted';
        $search = trim((string) $request->query('search', ''));
        $query = $this->registrationQuery($advisor)
            ->where('status', '!=', CourseRegistration::StatusDraft)
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->when($search !== '', fn ($query) => $query->whereHas('studentProfile', fn ($students) => $students
                ->where('registration_number', 'like', "%{$search}%")
                ->orWhereHas('studentProfile.user', fn ($users) => $users->where('name', 'like', "%{$search}%"))));
        $rows = $query->orderByDesc('submitted_at')->orderByDesc('id')->paginate(15)->withQueryString();

        return [
            'status' => $status,
            'search' => $search,
            'registrations' => $rows->through($this->registrationRow(...)),
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

    private function registrationQuery(AdvisorProfile $advisor)
    {
        return CourseRegistration::query()
            ->with(['studentProfile.user', 'studentProfile.studentSet', 'academicSessionSemester.academicSession.academicYear', 'academicSessionSemester.semester'])
            ->whereHas('studentProfile', fn ($query) => $query->where('advisor_profile_id', $advisor->id));
    }

    private function paymentQuery(AdvisorProfile $advisor)
    {
        return DuePayment::query()
            ->with(['studentProfile.user', 'studentProfile.studentSet', 'assignment.due'])
            ->where('advisor_profile_id', $advisor->id)
            ->where('status', DuePayment::StatusSuccess);
    }

    private function registrationRow(CourseRegistration $registration): array
    {
        $student = $registration->studentProfile;
        $semester = $registration->academicSessionSemester;

        return [
            'id' => (int) $registration->id,
            'studentName' => $student->user->name,
            'studentAvatar' => $student->user->profilePhotoUrl(),
            'studentUrl' => route('dashboard.students.show', ['registrationNumber' => $student->routeRegistrationNumber()]),
            'set' => $student->studentSet->label(),
            'levelSemester' => $semester->academicSession->academicYear->name.' '.$semester->semester->name,
            'session' => $semester->academicSession->label().' Session',
            'status' => match ($registration->status) {
                CourseRegistration::StatusApproved => 'Approved',
                CourseRegistration::StatusReturned => 'Changes requested',
                default => 'In review',
            },
            'submittedAt' => $registration->submitted_at?->toFormattedDateString(),
            'url' => route('dashboard.course-registration.show', $registration->reviewRouteParameters()),
        ];
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
