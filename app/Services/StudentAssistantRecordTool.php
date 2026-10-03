<?php

namespace App\Services;

use App\Models\CourseRegistration;
use App\Models\CourseRegistrationItem;
use App\Models\Due;
use App\Models\DuePayment;
use App\Models\StudentDueAssignment;
use App\Models\StudentProfile;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class StudentAssistantRecordTool implements Tool
{
    /** @var array<string, array{title: string, url: string}> */
    private array $sources = [];

    public function __construct(
        private readonly StudentProfile $student,
        private readonly CurrencyFormatter $currency,
        private readonly StudentResultAccess $resultAccess,
        private readonly ResultDashboardData $resultData,
    ) {}

    public function description(): Stringable|string
    {
        return 'Read only this student\'s identity, assigned courses, published and unlocked results, GPA history, dues, and completed payments or receipts. Use further pages before claiming a list is complete.';
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'category' => $schema->string()->enum(['profile', 'registrations', 'results', 'performance', 'dues', 'payments'])->required(),
            'term' => $schema->string(),
            'status' => $schema->string()->enum(['any', 'paid', 'unpaid', 'waived']),
            'session' => $schema->string(),
            'semester' => $schema->string()->enum(['first', 'second']),
            'page' => $schema->integer()->min(1),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        $term = trim(mb_substr((string) ($request['term'] ?? ''), 0, 80));
        $session = preg_replace('/\D/', '', (string) ($request['session'] ?? ''));
        $filters = [
            'term' => $term,
            'session' => $session === '' ? null : (strlen($session) === 8 ? $session : 'invalid'),
            'semester' => (string) ($request['semester'] ?? ''),
            'status' => (string) ($request['status'] ?? 'any'),
            'page' => min(100, max(1, (int) ($request['page'] ?? 1))),
        ];
        $result = match ((string) ($request['category'] ?? '')) {
            'profile' => $this->profile($filters),
            'registrations' => $this->registrations($filters),
            'results' => $this->results($filters),
            'performance' => $this->performance($filters),
            'dues' => $this->dues($filters),
            'payments' => $this->payments($filters),
            default => $this->result(0, [], $filters['page']),
        };

        foreach ($result['records'] as $record) {
            $this->sources[$record['url']] = ['title' => $record['title'], 'url' => $record['url']];
        }

        return json_encode($result, JSON_THROW_ON_ERROR);
    }

    /** @return list<array{title: string, url: string}> */
    public function sources(): array
    {
        return array_values($this->sources);
    }

    private function profile(array $filters): array
    {
        $student = $this->student->loadMissing('user', 'studentSet.currentAcademicYear', 'studentSet.currentSemester', 'studentSet.advisorProfile.user');
        $set = $student->studentSet;

        return $this->result(1, $filters['page'] === 1 ? [[
            'title' => $student->official_name,
            'detail' => $student->registration_number.' · '.$student->user?->email.' · '.$set->label().' · '.($set->currentAcademicYear?->name ?? 'No current level').' · '.($set->currentSemester?->name ?? 'No current semester').' · Adviser '.$set->advisorProfile->user->displayName(),
            'url' => route('dashboard.section', ['section' => 'profile-settings']),
        ]] : [], $filters['page']);
    }

    private function registrations(array $filters): array
    {
        $query = $this->student->courseRegistrations()
            ->where('status', CourseRegistration::StatusAssigned)
            ->when($filters['term'] !== '', fn ($query) => $query->whereHas('items', fn ($items) => $items
                ->where('course_code', 'like', '%'.$filters['term'].'%')
                ->orWhere('course_title', 'like', '%'.$filters['term'].'%')))
            ->whereHas('academicSessionSemester', fn ($period) => $this->filterPeriod($period, $filters));
        $count = $query->count();
        $records = $query->with(['items', 'academicSessionSemester.academicSession.academicYear', 'academicSessionSemester.semester'])
            ->latest('id')->forPage($filters['page'], 20)->get()
            ->map(function (CourseRegistration $registration): array {
                $semester = $registration->academicSessionSemester;
                $session = $semester->academicSession;

                return [
                    'title' => $session->academicYear->name.' '.$semester->semester->name,
                    'detail' => $session->label().' Session · '.$registration->items->sum('credit_units').' units · '.$registration->items->pluck('course_code')->join(', '),
                    'url' => route('dashboard.section', [
                        'section' => 'courses',
                        'session' => $session->routeYears(),
                        'semester' => $semester->semester->slug,
                    ]),
                ];
            })->all();

        return $this->result($count, $records, $filters['page']);
    }

    private function results(array $filters): array
    {
        $accessibleSemesters = $this->resultAccess->accessibleSemesterIds($this->student);
        $query = CourseRegistrationItem::query()
            ->where(fn ($items) => $items->whereNotNull('grade')->orWhere('missing_script', true))
            ->whereHas('courseRegistration', fn ($registrations) => $registrations
                ->where('student_profile_id', $this->student->id)
                ->where('status', CourseRegistration::StatusAssigned)
                ->whereIn('academic_session_semester_id', $accessibleSemesters)
                ->whereHas('academicSessionSemester', fn ($period) => $this->filterPeriod($period, $filters)))
            ->whereHas('courseOffering', fn ($offerings) => $offerings->whereNotNull('results_published_at'))
            ->when($filters['term'] !== '', fn ($items) => $items->where(fn ($query) => $query
                ->where('course_code', 'like', '%'.$filters['term'].'%')
                ->orWhere('course_title', 'like', '%'.$filters['term'].'%')));
        $count = $query->count();
        $records = $query->with(['courseRegistration.academicSessionSemester.academicSession', 'courseRegistration.academicSessionSemester.semester'])
            ->latest('id')->forPage($filters['page'], 20)->get()
            ->map(function (CourseRegistrationItem $item): array {
                $semester = $item->courseRegistration->academicSessionSemester;

                return [
                    'title' => $item->course_code.' '.$item->course_title,
                    'detail' => $semester->academicSession->label().' Session · '.$semester->semester->name.' · '.$item->credit_units.' units · '.($item->missing_script ? 'Missing script' : 'Grade '.$item->grade),
                    'url' => route('dashboard.section', [
                        'section' => 'results',
                        'session' => $semester->academicSession->routeYears(),
                        'semester' => $semester->semester->slug,
                    ]),
                ];
            })->all();

        return $this->result($count, $records, $filters['page']);
    }

    private function performance(array $filters): array
    {
        $periods = collect($this->resultData->studentResults($this->student, null, null, false)['periods'])
            ->filter(fn (array $period): bool => ($filters['session'] === null || $period['sessionYears'] === $filters['session'])
                && ($filters['semester'] === '' || $period['semester'] === $filters['semester'])
                && ($filters['term'] === '' || str_contains(mb_strtolower($period['label']), mb_strtolower($filters['term']))))
            ->values();

        return $this->result($periods->count(), $periods->forPage($filters['page'], 20)
            ->map(fn (array $period): array => [
                'title' => $period['label'],
                'detail' => $period['locked']
                    ? 'Results locked by '.$period['unpaidDueCount'].' unpaid due(s).'
                    : 'GPA '.($period['gpa'] ?? 'Not available').' · CGPA '.($period['cgpa'] ?? 'Not available'),
                'url' => $period['url'],
            ])->values()->all(), $filters['page']);
    }

    private function dues(array $filters): array
    {
        $query = $this->student->dueAssignments()
            ->whereHas('due', fn ($dues) => $this->filterDuePeriod($dues
                ->where('status', Due::StatusPublished)
                ->when($filters['term'] !== '', fn ($query) => $query->where('title', 'like', '%'.$filters['term'].'%')), $filters))
            ->when(in_array($filters['status'], ['paid', 'unpaid', 'waived'], true),
                fn ($query) => $query->where('status', $filters['status'] === 'unpaid' ? StudentDueAssignment::StatusPending : $filters['status']));
        $count = $query->count();
        $records = $query->with('due')->latest('id')->forPage($filters['page'], 20)->get()
            ->map(fn (StudentDueAssignment $assignment): array => [
                'title' => $assignment->due->title,
                'detail' => $this->currency->nairaFromKobo((int) $assignment->amount_kobo).' · '.match ($assignment->status) {
                    StudentDueAssignment::StatusPaid => 'Paid',
                    StudentDueAssignment::StatusWaived => 'Waived',
                    default => 'Unpaid',
                },
                'url' => route('dashboard.section', ['section' => 'dues']),
            ])->all();

        return $this->result($count, $records, $filters['page']);
    }

    private function payments(array $filters): array
    {
        $query = $this->student->duePayments()->where('status', DuePayment::StatusSuccess)
            ->whereHas('assignment.due', fn ($dues) => $this->filterDuePeriod($dues
                ->when($filters['term'] !== '', fn ($query) => $query->where('title', 'like', '%'.$filters['term'].'%')), $filters));

        return $this->result($query->count(), $query->with('assignment.due')
            ->orderByDesc('paid_at')->forPage($filters['page'], 20)->get()
            ->map(fn (DuePayment $payment): array => [
                'title' => $payment->assignment->due->title,
                'detail' => $this->currency->nairaFromKobo((int) $payment->amount_kobo).' · Paid '.$payment->paid_at?->toDateString().' · Receipt '.$payment->receipt_status,
                'url' => $payment->paystack_transaction_id && $payment->receipt_status === DuePayment::ReceiptStatusReady
                    ? route('dashboard.dues.payments.receipt', ['paystackTransactionId' => $payment->paystack_transaction_id])
                    : route('dashboard.section', ['section' => 'dues']),
            ])->all(), $filters['page']);
    }

    private function filterPeriod(Builder $period, array $filters): Builder
    {
        return $period
            ->when($filters['session'] !== null, fn ($query) => $query->whereHas('academicSession', fn ($session) => $session
                ->where('start_year', (int) substr($filters['session'], 0, 4))
                ->where('end_year', (int) substr($filters['session'], 4, 4))))
            ->when($filters['semester'] !== '', fn ($query) => $query->whereHas('semester',
                fn ($semester) => $semester->where('slug', $filters['semester'])));
    }

    private function filterDuePeriod(Builder $dues, array $filters): Builder
    {
        return $dues
            ->when($filters['session'] !== null, fn ($query) => $query->whereHas('academicSession', fn ($session) => $session
                ->where('start_year', (int) substr($filters['session'], 0, 4))
                ->where('end_year', (int) substr($filters['session'], 4, 4))))
            ->when($filters['semester'] !== '', fn ($query) => $query->where(fn ($period) => $period
                ->whereNull('academic_session_semester_id')
                ->orWhereHas('academicSessionSemester.semester',
                    fn ($semester) => $semester->where('slug', $filters['semester']))));
    }

    private function result(int $count, array $records, int $page): array
    {
        return ['count' => $count, 'records' => $records, 'page' => $page, 'moreAvailable' => $count > $page * 20];
    }
}
