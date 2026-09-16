<?php

namespace App\Services\Spreadsheet;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SpreadsheetExportService
{
    public const string ContentType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    public function download(Spreadsheet $spreadsheet, string $fileName): StreamedResponse
    {
        return response()->streamDownload(function () use ($spreadsheet): void {
            try {
                $writer = new Xlsx($spreadsheet);
                $writer->setPreCalculateFormulas(false);
                $writer->save('php://output');
            } finally {
                $spreadsheet->disconnectWorksheets();
            }
        }, $fileName, [
            'Content-Type' => self::ContentType,
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }
}
