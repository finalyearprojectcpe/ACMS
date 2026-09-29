<?php

namespace App\Services;

use App\Models\CourseRegistration;
use App\Models\CourseRegistrationItem;
use App\Models\Due;
use App\Models\StudentDueAssignment;
use App\Models\StudentProfile;
use Illuminate\Contracts\JsonSchema\JsonSchema;
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
    ) {}

    public function description(): Stringable|string
    {
        return 'Search only this student\'s own course registrations, published course results, and assigned dues. Unpublished grades are not available.';
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'category' => $schema->string()->enum(['registrations', 'results', 'dues'])->required(),
            'term' => $schema->string()->required(),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        $term = trim(mb_substr((string) ($request['term'] ?? ''), 0, 80));
        $result = match ((string) ($request['category'] ?? '')) {
            'registrations' => $this->registrations($term),
            'results' => $this->results($term),
            'dues' => $this->dues($term),
            default => ['count' => 0, 'records' => [], 'moreAvailable' => false],
        };

        foreach ($result['records'] as $record) {
            $this->sources[$record['url']] = ['title' => $record['title'], 'url' => $record['url']];
        }

        return json_encode($result, JSON_THROW_ON_ERROR);
    }

    /** @return list<array{title: string, url: string}> */
    public function sources(): array
    {
        return array_slice(array_values($this->sources), 0, 6);
    }

    private function registrations(string $term): array
    {
        $query = $this->student->courseRegistrations()
            ->when($term !== '', fn ($query) => $query->whereHas('items', fn ($items) => $items
                ->where('course_code', 'like', '%'.$term.'%')
                ->orWhere('course_title', 'like', '%'.$term.'%')));
        $count = $query->count();
        $records = $query->with(['items', 'academicSessionSemester.academicSession.academicYear', 'academicSessionSemester.semester'])
            ->latest('id')->limit(20)->get()
            ->map(function (CourseRegistration $registration): array {
                $semester = $registration->academicSessionSemester;
                $session = $semester->academicSession;

                return [
                    'title' => $session->academicYear->name.' '.$semester->semester->name,
                    'detail' => $session->label().' Session · '.$registration->status.' · '.$registration->items->sum('credit_units').' units · '.$registration->items->pluck('course_code')->join(', '),
                    'url' => route('dashboard.section', [
                        'section' => 'course-registration',
                        'session' => $session->routeYears(),
                        'semester' => $semester->semester->slug,
                    ]),
                ];
            })->all();

        return $this->result($count, $records);
    }

    private function results(string $term): array
    {
        $query = CourseRegistrationItem::query()
            ->where(fn ($items) => $items->whereNotNull('grade')->orWhere('missing_script', true))
            ->whereHas('courseRegistration', fn ($registrations) => $registrations
                ->where('student_profile_id', $this->student->id)
                ->where('status', CourseRegistration::StatusApproved))
            ->whereHas('courseOffering', fn ($offerings) => $offerings->whereNotNull('results_published_at'))
            ->when($term !== '', fn ($items) => $items->where(fn ($query) => $query
                ->where('course_code', 'like', '%'.$term.'%')
                ->orWhere('course_title', 'like', '%'.$term.'%')));
        $count = $query->count();
        $records = $query->with(['courseRegistration.academicSessionSemester.academicSession', 'courseRegistration.academicSessionSemester.semester'])
            ->latest('id')->limit(20)->get()
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

        return $this->result($count, $records);
    }

    private function dues(string $term): array
    {
        $query = $this->student->dueAssignments()
            ->whereHas('due', fn ($dues) => $dues
                ->where('status', Due::StatusPublished)
                ->when($term !== '', fn ($query) => $query->where('title', 'like', '%'.$term.'%')));
        $count = $query->count();
        $records = $query->with('due')->latest('id')->limit(20)->get()
            ->map(fn (StudentDueAssignment $assignment): array => [
                'title' => $assignment->due->title,
                'detail' => $this->currency->nairaFromKobo((int) $assignment->amount_kobo).' · '.match ($assignment->status) {
                    StudentDueAssignment::StatusPaid => 'Paid',
                    StudentDueAssignment::StatusWaived => 'Waived',
                    default => 'Unpaid',
                },
                'url' => route('dashboard.section', ['section' => 'dues']),
            ])->all();

        return $this->result($count, $records);
    }

    private function result(int $count, array $records): array
    {
        return ['count' => $count, 'records' => $records, 'moreAvailable' => $count > count($records)];
    }
}
