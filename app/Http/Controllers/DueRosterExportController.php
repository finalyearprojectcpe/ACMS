<?php

namespace App\Http\Controllers;

use App\Http\Requests\DownloadDueRosterRequest;
use App\Models\Due;
use App\Services\DashboardAccess;
use App\Services\DueRosterWord;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DueRosterExportController extends Controller
{
    public function __construct(
        private readonly DashboardAccess $access,
        private readonly DueRosterWord $word,
    ) {}

    public function download(DownloadDueRosterRequest $request, Due $due): BinaryFileResponse
    {
        $this->access->ensureOwnsDue($this->access->advisor($request), $due);
        abort_if($due->status === Due::StatusDraft, 404);

        $path = tempnam(sys_get_temp_dir(), 'acms-due-roster-');
        if ($path === false) {
            throw new RuntimeException('Could not prepare the Word document.');
        }

        try {
            $status = $request->validated('status', 'all');
            $this->word->save($due, $path, $status);
        } catch (\Throwable $exception) {
            unlink($path);

            throw $exception;
        }

        return response()->download(
            $path,
            (Str::slug($due->title) ?: 'due').'-'.Str::slug($due->studentSet->label()).'-'.$due->academicSession->routeYears().'-'.$status.'-'.now()->format('Ymd').'.docx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        )->deleteFileAfterSend(true);
    }
}
