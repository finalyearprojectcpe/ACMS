<?php

namespace App\Services\Spreadsheet;

use App\Models\AdvisorProfile;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StudentClasslistWorkbook
{
    public function __construct(private readonly WorkbookStyler $styles) {}

    /**
     * @param  Collection<int, array{fullName: string, email: string|null, registrationNumber: string, setLabel: string, advisorName: string}>  $students
     * @param  array{scope: string, courseAdvisor: string|null, filters: string|null, includeContextColumns: bool}  $context
     */
    public function build(AdvisorProfile $advisor, Collection $students, array $context): Spreadsheet
    {
        $advisor->loadMissing('user');
        $includeContextColumns = $context['includeContextColumns'];
        $endColumn = $includeContextColumns ? 'F' : 'D';
        $spreadsheet = $this->styles->workbook(
            $advisor->programme_abbreviation.' Student Classlist',
            'Filtered student classlist for '.$advisor->programme_name,
        );
        $sheet = $spreadsheet->getActiveSheet();

        $this->styles->prepareSheet($sheet, 'Classlist', PageSetup::ORIENTATION_LANDSCAPE);
        $sheet->mergeCells("A1:{$endColumn}1");
        $this->styles->text($sheet, 'A1', $advisor->institution_name ?: config('app.name'));
        $this->styles->documentSubtitle($sheet, "A1:{$endColumn}1");
        $sheet->mergeCells("A2:{$endColumn}2");
        $this->styles->text($sheet, 'A2', $advisor->programme_abbreviation.' Student Classlist');
        $this->styles->documentTitle($sheet, "A2:{$endColumn}2");
        $sheet->mergeCells("A3:{$endColumn}3");
        $this->styles->text($sheet, 'A3', $advisor->programme_name.' · Course Adviser '.$advisor->user->name);
        $sheet->getStyle("A3:{$endColumn}3")->getFont()->setSize(10)->getColor()->setARGB(WorkbookStyler::Muted);
        $sheet->mergeCells("A4:{$endColumn}4");
        $sheet->setCellValue('A4', 'Generated '.now()->format('d M Y, H:i'));
        $sheet->getStyle("A4:{$endColumn}4")->getFont()->setSize(9)->getColor()->setARGB(WorkbookStyler::Muted);

        $this->writeMetadata($sheet, 5, 'Scope', $context['scope'], $endColumn);
        $this->writeMetadata($sheet, 6, 'Students', (string) $students->count(), $endColumn);

        if ($context['courseAdvisor'] !== null) {
            $this->writeMetadata($sheet, 7, 'Course Adviser', $context['courseAdvisor'], $endColumn);
        }

        if ($context['filters'] !== null) {
            $this->writeMetadata($sheet, 8, 'Applied Filters', $context['filters'], $endColumn);
        }

        $headerRow = 10;
        $headings = ['S/N', 'Full Name', 'Registration Number', 'Email'];

        if ($includeContextColumns) {
            $headings[] = 'Student Set';
            $headings[] = 'Course Adviser';
        }

        foreach ($headings as $index => $heading) {
            $sheet->setCellValue([$index + 1, $headerRow], $heading);
        }

        $this->styles->tableHeader($sheet, "A{$headerRow}:{$endColumn}{$headerRow}");
        $sheet->getRowDimension($headerRow)->setRowHeight(28);
        $row = $headerRow + 1;

        foreach ($students as $index => $student) {
            $sheet->setCellValue("A{$row}", $index + 1);
            $this->styles->text($sheet, "B{$row}", $student['fullName']);
            $this->styles->text($sheet, "C{$row}", $student['registrationNumber']);
            $this->styles->text($sheet, "D{$row}", $student['email']);

            if ($includeContextColumns) {
                $this->styles->text($sheet, "E{$row}", $student['setLabel']);
                $this->styles->text($sheet, "F{$row}", $student['advisorName']);
            }

            $row++;
        }

        $dataStartRow = $headerRow + 1;
        $this->styles->tableBody($sheet, $dataStartRow, $row - 1, $endColumn);

        if ($students->isEmpty()) {
            $sheet->mergeCells("A{$row}:{$endColumn}".($row + 1));
            $sheet->setCellValue("A{$row}", 'No students match the selected filters.');
            $sheet->getStyle("A{$row}:{$endColumn}".($row + 1))->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle("A{$row}:{$endColumn}".($row + 1))->getFont()->getColor()->setARGB(WorkbookStyler::Muted);
            $row += 2;
        }

        $this->styles->columnWidths($sheet, [
            'A' => 7,
            'B' => 32,
            'C' => 23,
            'D' => 34,
            ...($includeContextColumns ? ['E' => 28, 'F' => 30] : []),
        ]);
        $sheet->freezePane("A{$dataStartRow}");
        $sheet->setAutoFilter("A{$headerRow}:{$endColumn}{$headerRow}");
        $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd($headerRow, $headerRow);
        $sheet->getPageSetup()->setPrintArea("A1:{$endColumn}".max($row - 1, $headerRow));

        return $spreadsheet;
    }

    private function writeMetadata(Worksheet $sheet, int $row, string $label, string $value, string $endColumn): void
    {
        $sheet->setCellValue("A{$row}", $label);
        $sheet->mergeCells("B{$row}:{$endColumn}{$row}");
        $this->styles->text($sheet, "B{$row}", $value);
        $this->styles->metadataLabel($sheet, "A{$row}");
        $this->styles->metadataValue($sheet, "B{$row}:{$endColumn}{$row}");
    }
}
