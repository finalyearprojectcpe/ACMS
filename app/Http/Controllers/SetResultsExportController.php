<?php

namespace App\Http\Controllers;

use App\Http\Requests\DownloadSetResultsRequest;
use App\Services\DashboardAccess;
use App\Services\SetResultsExport;
use App\Services\SetResultsSpreadsheet;
use App\Services\Spreadsheet\SpreadsheetExportService;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SetResultsExportController extends Controller
{
    public function __construct(
        private readonly DashboardAccess $access,
        private readonly SetResultsExport $reports,
        private readonly SetResultsSpreadsheet $spreadsheet,
        private readonly SpreadsheetExportService $downloads,
    ) {}

    public function download(DownloadSetResultsRequest $request, string $departmentSlug, string $entrySession): StreamedResponse
    {
        $set = $this->access->studentSetByRoute($this->access->advisor($request), $departmentSlug, $entrySession);
        $filters = $request->validated();
        abort_if(isset($filters['semester']) && ! isset($filters['session']), 404);
        abort_if(isset($filters['all']) && (isset($filters['session']) || isset($filters['semester'])), 404);
        $report = $this->reports->build($set, $filters['session'] ?? null, $filters['semester'] ?? null, isset($filters['all']));
        abort_if($report['groups'] === [], 404);

        $scope = isset($filters['all']) ? 'all-history' : Str::slug($report['groups'][0]['session'].'-'.$report['groups'][0]['label']);
        $name = Str::slug($set->label()).'-'.$scope.'-results';

        return $this->downloads->download($this->spreadsheet->build($report), $name.'.xlsx');
    }
}
