<?php

namespace App\Services\Spreadsheet;

use App\Models\Due;
use App\Models\DuePayment;
use App\Models\StudentDueAssignment;
use App\Services\AcademicContextService;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

class DuePaymentsWorkbook
{
    public function __construct(
        private readonly AcademicContextService $academicContext,
        private readonly WorkbookStyler $styles,
    ) {}

    public function build(Due $due): Spreadsheet
    {
        $due->loadMissing([
            'advisorProfile.user',
            'studentSet.entryAcademicSession',
            'academicSession',
            'assignments.studentProfile.user',
            'assignments.latestPayment',
        ]);
        $assignments = $due->assignments
            ->sortBy(fn (StudentDueAssignment $assignment): string => sprintf(
                '%02d-%s',
                $this->statusSortOrder($assignment->status),
                $assignment->studentProfile?->user?->name ?? '',
            ))
            ->values();
        $collectedKobo = (int) $assignments->sum(fn (StudentDueAssignment $assignment): int => $this->paidAmountKobo($assignment));
        $outstandingKobo = (int) $assignments
            ->where('status', StudentDueAssignment::StatusPending)
            ->sum('amount_kobo');
        $spreadsheet = $this->styles->workbook($due->title.' Payments', 'Payment status and reconciliation for '.$due->title);
        $sheet = $spreadsheet->getActiveSheet();

        $this->styles->prepareSheet($sheet, 'Payments', PageSetup::ORIENTATION_LANDSCAPE);
        $sheet->mergeCells('A1:I1');
        $this->styles->text($sheet, 'A1', $due->advisorProfile?->institution_name ?: config('app.name'));
        $this->styles->documentSubtitle($sheet, 'A1:I1');
        $sheet->mergeCells('A2:I2');
        $this->styles->text($sheet, 'A2', $due->title.' Payments');
        $this->styles->documentTitle($sheet, 'A2:I2');
        $sheet->mergeCells('A3:I3');
        $this->styles->text($sheet, 'A3', $this->dueContext($due));
        $sheet->getStyle('A3:I3')->getFont()->setSize(10)->getColor()->setARGB(WorkbookStyler::Muted);
        $sheet->mergeCells('A4:I4');
        $sheet->setCellValue('A4', 'Generated '.now()->format('d M Y, H:i'));
        $sheet->getStyle('A4:I4')->getFont()->setSize(9)->getColor()->setARGB(WorkbookStyler::Muted);

        $summary = [
            ['Assigned', $assignments->count()],
            ['Paid', $assignments->where('status', StudentDueAssignment::StatusPaid)->count()],
            ['Pending', $assignments->where('status', StudentDueAssignment::StatusPending)->count()],
            ['Waived', $assignments->where('status', StudentDueAssignment::StatusWaived)->count()],
        ];

        foreach ($summary as $index => [$label, $value]) {
            $startColumn = chr(ord('A') + ($index * 2));
            $valueColumn = chr(ord($startColumn) + 1);
            $sheet->setCellValue("{$startColumn}6", $label);
            $sheet->setCellValue("{$valueColumn}6", $value);
            $this->styles->metadataLabel($sheet, "{$startColumn}6");
            $this->styles->metadataValue($sheet, "{$valueColumn}6");
        }

        $sheet->setCellValue('A7', 'Collected');
        $sheet->setCellValue('B7', $collectedKobo / 100);
        $sheet->setCellValue('D7', 'Outstanding');
        $sheet->setCellValue('E7', $outstandingKobo / 100);
        $sheet->setCellValue('G7', 'Standard Amount');
        $sheet->setCellValue('H7', $due->amount_kobo / 100);
        $this->styles->metadataLabel($sheet, 'A7');
        $this->styles->metadataLabel($sheet, 'D7');
        $this->styles->metadataLabel($sheet, 'G7');
        $this->styles->metadataValue($sheet, 'B7');
        $this->styles->metadataValue($sheet, 'E7');
        $this->styles->metadataValue($sheet, 'H7');
        foreach (['B7', 'E7', 'H7'] as $coordinate) {
            $sheet->getStyle($coordinate)->getNumberFormat()->setFormatCode(WorkbookStyler::CurrencyFormat);
        }

        $headerRow = 9;
        $headings = ['S/N', 'Student', 'Registration Number', 'Status', 'Amount Due', 'Amount Paid', 'Paid At', 'Receipt Number', 'Payment Reference'];

        foreach ($headings as $index => $heading) {
            $sheet->setCellValue([$index + 1, $headerRow], $heading);
        }

        $this->styles->tableHeader($sheet, "A{$headerRow}:I{$headerRow}");
        $sheet->getRowDimension($headerRow)->setRowHeight(28);
        $row = $headerRow + 1;

        foreach ($assignments as $index => $assignment) {
            $payment = $assignment->latestPayment;
            $sheet->setCellValue("A{$row}", $index + 1);
            $this->styles->text($sheet, "B{$row}", $assignment->studentProfile?->user?->name ?? 'Student');
            $this->styles->text($sheet, "C{$row}", $assignment->studentProfile?->registration_number);
            $this->styles->status($sheet, "D{$row}", Str::headline($assignment->status));
            $sheet->setCellValue("E{$row}", $assignment->amount_kobo / 100);

            if ($payment?->status === DuePayment::StatusSuccess) {
                $sheet->setCellValue("F{$row}", $payment->amount_kobo / 100);
                $this->styles->date($sheet, "G{$row}", $payment->paid_at, true);
                $this->styles->text($sheet, "H{$row}", $payment->receipt_number);
                $this->styles->text($sheet, "I{$row}", $payment->reference);
            }

            $row++;
        }

        $dataStartRow = $headerRow + 1;
        $this->styles->tableBody($sheet, $dataStartRow, $row - 1, 'I');
        if ($row > $dataStartRow) {
            $sheet->getStyle("E{$dataStartRow}:F".($row - 1))->getNumberFormat()->setFormatCode(WorkbookStyler::CurrencyFormat);
        }

        if ($assignments->isEmpty()) {
            $sheet->mergeCells("A{$row}:I".($row + 1));
            $sheet->setCellValue("A{$row}", 'No students have been assigned this due.');
            $sheet->getStyle("A{$row}:I".($row + 1))->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle("A{$row}:I".($row + 1))->getFont()->getColor()->setARGB(WorkbookStyler::Muted);
            $row += 2;
        } else {
            $sheet->mergeCells("A{$row}:D{$row}");
            $sheet->setCellValue("A{$row}", 'Totals');
            $sheet->setCellValue("E{$row}", $assignments->sum('amount_kobo') / 100);
            $sheet->setCellValue("F{$row}", $collectedKobo / 100);
            $this->styles->summaryRow($sheet, "A{$row}:I{$row}");
            $sheet->getStyle("E{$row}:F{$row}")->getNumberFormat()->setFormatCode(WorkbookStyler::CurrencyFormat);
        }

        $this->styles->columnWidths($sheet, [
            'A' => 7,
            'B' => 30,
            'C' => 22,
            'D' => 12,
            'E' => 16,
            'F' => 16,
            'G' => 22,
            'H' => 24,
            'I' => 28,
        ]);
        $sheet->freezePane("A{$dataStartRow}");
        $sheet->setAutoFilter("A{$headerRow}:I{$headerRow}");
        $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd($headerRow, $headerRow);
        $sheet->getPageSetup()->setPrintArea("A1:I{$row}");

        return $spreadsheet;
    }

    private function paidAmountKobo(StudentDueAssignment $assignment): int
    {
        return $assignment->latestPayment?->status === DuePayment::StatusSuccess
            ? (int) $assignment->latestPayment->amount_kobo
            : 0;
    }

    private function statusSortOrder(string $status): int
    {
        return match ($status) {
            StudentDueAssignment::StatusPending => 1,
            StudentDueAssignment::StatusPaid => 2,
            StudentDueAssignment::StatusWaived => 3,
            default => 4,
        };
    }

    private function dueContext(Due $due): string
    {
        $level = $due->studentSet === null
            ? null
            : $this->academicContext->academicYearForSet($due->studentSet, $due->academicSession);
        $set = $due->studentSet?->name ?? 'Unknown set';
        $levelLabel = $level === null ? null : $this->styles->shortLevel($level->name);
        $parts = array_filter([
            $due->advisorProfile?->programme_name,
            $set.($levelLabel === null ? '' : ' · '.$levelLabel),
            $due->academicSession?->name === null ? null : $due->academicSession->name.' Session',
        ]);

        return implode(' · ', $parts);
    }
}
