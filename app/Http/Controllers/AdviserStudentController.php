<?php

namespace App\Http\Controllers;

use App\Models\StudentProfile;
use App\Services\DashboardAccess;
use App\Services\Spreadsheet\SpreadsheetExportService;
use App\Services\Spreadsheet\StudentClasslistWorkbook;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdviserStudentController extends Controller
{
    public function __construct(private readonly DashboardAccess $access) {}

    public function exportClasslist(
        Request $request,
        StudentClasslistWorkbook $workbook,
        SpreadsheetExportService $exports,
    ): StreamedResponse {
        $advisor = $this->access->advisor($request);
        $requestedSet = $request->query('set');
        abort_if($requestedSet !== null && ! is_string($requestedSet), 404);
        $set = $requestedSet === null
            ? null
            : $this->access->studentSetByFilterKey($advisor, $requestedSet);
        $setId = $set?->id;
        $students = $advisor->students()
            ->with(['user', 'studentSet'])
            ->when($set, fn ($query) => $query->where('student_set_id', $setId))
            ->get()
            ->sortBy(fn (StudentProfile $student): string => $student->user->name)
            ->values()
            ->map(fn (StudentProfile $student): array => [
                'fullName' => $student->user->name,
                'email' => $student->user->email,
                'registrationNumber' => $student->registration_number,
                'setLabel' => $student->studentSet->label(),
                'advisorName' => $advisor->user->displayName(),
            ]);
        $scope = $set
            ? $set->label()
            : 'All student sets';

        return $exports->download(
            $workbook->build($advisor, $students, [
                'scope' => $scope,
                'courseAdvisor' => $advisor->user->displayName(),
                'filters' => null,
                'includeContextColumns' => $setId === 0,
            ]),
            'student-classlist-'.now()->format('Y-m-d').'.xlsx',
        );
    }
}
