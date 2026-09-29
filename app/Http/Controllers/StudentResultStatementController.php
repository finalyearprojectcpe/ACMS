<?php

namespace App\Http\Controllers;

use App\Models\StudentProfile;
use App\Services\DashboardAccess;
use App\Services\ResultStatementWord;
use App\Services\StudentResultStatement;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StudentResultStatementController extends Controller
{
    public function __construct(
        private readonly DashboardAccess $access,
        private readonly StudentResultStatement $statements,
        private readonly ResultStatementWord $word,
    ) {}

    public function download(Request $request, string $registrationNumber): BinaryFileResponse
    {
        $student = $this->access->advisor($request)->students()
            ->where('registration_number', StudentProfile::registrationNumberFromRoute($registrationNumber))
            ->firstOrFail();
        $statement = $this->statements->build($student);
        abort_if($statement['periods'] === [], 404);

        $path = tempnam(sys_get_temp_dir(), 'gm-statement-');
        if ($path === false) {
            throw new RuntimeException('Could not prepare the Word document.');
        }

        try {
            $this->word->save($statement, $path);
        } catch (\Throwable $exception) {
            unlink($path);

            throw $exception;
        }

        return response()->download(
            $path,
            'results-to-date-'.(Str::slug($student->registration_number) ?: 'student').'.docx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        )->deleteFileAfterSend(true);
    }
}
