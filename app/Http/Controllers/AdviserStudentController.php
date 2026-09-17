<?php

namespace App\Http\Controllers;

use App\AccountStatus;
use App\AccountType;
use App\Http\Requests\ImportAdvisedStudentsRequest;
use App\Http\Requests\StoreAdvisedStudentRequest;
use App\Models\AdvisorProfile;
use App\Models\ImportBatch;
use App\Models\StudentProfile;
use App\Models\StudentSet;
use App\Models\User;
use App\Services\AcademicContextService;
use App\Services\AccessCodeGenerator;
use App\Services\DashboardAccess;
use App\Services\DueAssignmentService;
use App\Services\Spreadsheet\SpreadsheetExportService;
use App\Services\Spreadsheet\StudentClasslistWorkbook;
use App\StudentStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdviserStudentController extends Controller
{
    public function __construct(
        private readonly DashboardAccess $access,
        private readonly AcademicContextService $academicContext,
        private readonly DueAssignmentService $dueAssignments,
        private readonly AccessCodeGenerator $accessCodes,
    ) {}

    public function store(StoreAdvisedStudentRequest $request): RedirectResponse
    {
        $advisor = $this->access->advisor($request);
        $set = $this->access->studentSet($advisor, $request->integer('student_set_id'));
        $validated = $request->validated();
        $student = DB::transaction(fn (): StudentProfile => $this->provisionStudent(
            $advisor,
            $set,
            (string) $validated['registration_number'],
            $validated['access_code'] ?? null,
        ));

        return to_route('dashboard.section', ['section' => 'students'])
            ->with('success', "Student {$student->registration_number} added. Their access code is ready to copy.");
    }

    public function import(ImportAdvisedStudentsRequest $request): RedirectResponse
    {
        $advisor = $this->access->advisor($request);
        $set = $this->access->studentSet($advisor, $request->integer('student_set_id'));
        $validated = $request->validated();
        $rows = collect($validated['rows']);
        $batchNumber = (int) ($validated['batch_number'] ?? 1);
        $startRow = (int) ($validated['start_row'] ?? 1);
        $totalRows = (int) ($validated['total_rows'] ?? $rows->count());
        $totalBatches = (int) ($validated['total_batches'] ?? 1);

        $importedCount = DB::transaction(function () use ($advisor, $set, $request, $validated, $rows, $batchNumber, $startRow, $totalRows, $totalBatches): int {
            $batch = ImportBatch::query()->firstOrCreate([
                'advisor_profile_id' => $advisor->id,
                'uploaded_by_user_id' => $request->user()->id,
                'type' => 'student_import',
                'import_key' => $validated['import_key'] ?: null,
            ], [
                'student_set_id' => $set->id,
                'status' => 'completed',
                'file_name' => $validated['file_name'] ?: 'Students spreadsheet',
                'mappings' => $validated['mappings'] ?? [],
                'summary' => [],
                'submitted_at' => now(),
                'approved_at' => now(),
            ]);

            foreach ($rows as $index => $row) {
                $rowNumber = $startRow + $index;

                if ($batch->rows()->where('row_number', $rowNumber)->where('status', 'imported')->exists()) {
                    continue;
                }

                $student = $this->provisionStudent(
                    $advisor,
                    $set,
                    (string) $row['registrationNumber'],
                    $row['accessCode'] ?: null,
                );
                $batch->rows()->updateOrCreate(['row_number' => $rowNumber], [
                    'raw_data' => $row,
                    'normalized_data' => [
                        'student_profile_id' => $student->id,
                        'registration_number' => $student->registration_number,
                        'access_code' => $student->user->access_code,
                    ],
                    'errors' => [],
                    'status' => 'imported',
                ]);
            }

            $count = $batch->rows()->where('status', 'imported')->count();
            $batch->forceFill([
                'student_set_id' => $set->id,
                'mappings' => $validated['mappings'] ?? $batch->mappings,
                'summary' => [
                    'submitted_rows' => $totalRows,
                    'total_batches' => $totalBatches,
                    'processed_batches' => $batchNumber,
                    'imported_students' => $count,
                ],
            ])->save();

            return $count;
        });

        if ($batchNumber < $totalBatches) {
            return back()->with('success', "Batch {$batchNumber} of {$totalBatches} imported. Continue with the next batch.");
        }

        return to_route('dashboard.section', ['section' => 'students'])
            ->with('success', "{$importedCount} student records imported.");
    }

    public function exportClasslist(
        Request $request,
        StudentClasslistWorkbook $workbook,
        SpreadsheetExportService $exports,
    ): StreamedResponse {
        $advisor = $this->access->advisor($request);
        $setId = $request->integer('set');
        $students = $advisor->students()
            ->with(['user', 'studentSet.entryAcademicSession'])
            ->when($setId > 0, fn ($query) => $query->where('student_set_id', $setId))
            ->get()
            ->sortBy(fn (StudentProfile $student): string => $student->user->name)
            ->values()
            ->map(fn (StudentProfile $student): array => [
                'fullName' => $student->user->name,
                'email' => $student->user->email,
                'registrationNumber' => $student->registration_number,
                'setLabel' => $student->studentSet->name,
                'advisorName' => $advisor->user->name,
            ]);
        $scope = $setId > 0
            ? $advisor->studentSets()->findOrFail($setId)->name
            : 'All student sets';

        return $exports->download(
            $workbook->build($advisor, $students, [
                'scope' => $scope,
                'courseAdvisor' => $advisor->user->name,
                'filters' => null,
                'includeContextColumns' => $setId === 0,
            ]),
            Str::lower($advisor->programme_abbreviation).'-student-classlist-'.now()->format('Y-m-d').'.xlsx',
        );
    }

    private function provisionStudent(
        AdvisorProfile $advisor,
        StudentSet $set,
        string $registrationNumber,
        ?string $accessCode,
    ): StudentProfile {
        $existing = $advisor->students()
            ->where('registration_number', $registrationNumber)
            ->first();

        if ($existing instanceof StudentProfile) {
            return $existing->load('user');
        }

        $user = User::query()->create([
            'name' => 'Student '.$registrationNumber,
            'email' => null,
            'password' => Str::password(32),
            'access_code' => $accessCode ?: $this->accessCodes->generate(),
            'status' => AccountStatus::Pending,
            'account_type' => AccountType::Student,
        ]);

        $student = StudentProfile::query()->create([
            'user_id' => $user->id,
            'advisor_profile_id' => $advisor->id,
            'student_set_id' => $set->id,
            'current_academic_year_id' => $this->academicContext->academicYearForSet($set)?->id,
            'registration_number' => $registrationNumber,
            'status' => StudentStatus::Active,
        ])->load('user');
        $this->dueAssignments->syncForStudent($student);

        return $student;
    }
}
