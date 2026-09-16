<?php

namespace App\Services\Spreadsheet;

use App\Models\ResultSheet;
use App\Models\ResultSheetCourse;
use App\Models\StudentCourseResult;
use App\Models\StudentSemesterResult;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

class ResultSheetWorkbook
{
    public function __construct(private readonly WorkbookStyler $styles) {}

    public function build(ResultSheet $resultSheet): Spreadsheet
    {
        $resultSheet->loadMissing([
            'advisorProfile.user',
            'academicSession',
            'studentSet.entryAcademicSession',
            'academicYearSemester.academicYear',
            'academicYearSemester.semester',
            'createdBy',
            'courses.courseOffering.academicYearSemester',
            'studentResults.studentProfile.user',
            'studentResults.courseResults',
        ]);
        $courses = $resultSheet->courses->values();
        $rows = $resultSheet->studentResults
            ->sortBy(fn (StudentSemesterResult $result): string => $result->studentProfile?->user?->name ?? '')
            ->values();
        $courseStartColumnIndex = 4;
        $gpaColumnIndex = $courseStartColumnIndex + $courses->count();
        $courseEndColumnIndex = max($courseStartColumnIndex, $gpaColumnIndex - 1);
        $courseStartColumn = Coordinate::stringFromColumnIndex($courseStartColumnIndex);
        $courseEndColumn = Coordinate::stringFromColumnIndex($courseEndColumnIndex);
        $gpaColumn = Coordinate::stringFromColumnIndex($gpaColumnIndex);
        $endColumn = $gpaColumn;
        $level = $resultSheet->academicYearSemester->academicYear;
        $semester = $resultSheet->academicYearSemester->semester;
        $qualifiedSemester = $this->styles->qualifiedSemester($level->name, $semester->name);
        $title = $qualifiedSemester.' Results';
        $spreadsheet = $this->styles->workbook($title, 'Result sheet for '.$resultSheet->studentSet->name);
        $sheet = $spreadsheet->getActiveSheet();

        $this->styles->prepareSheet($sheet, 'Result Sheet', PageSetup::ORIENTATION_LANDSCAPE);
        $sheet->mergeCells("A1:{$endColumn}1");
        $this->styles->text($sheet, 'A1', $resultSheet->advisorProfile?->institution_name ?: config('app.name'));
        $this->styles->documentSubtitle($sheet, "A1:{$endColumn}1");
        $sheet->mergeCells("A2:{$endColumn}2");
        $this->styles->text($sheet, 'A2', $title);
        $this->styles->documentTitle($sheet, "A2:{$endColumn}2");
        $sheet->mergeCells("A3:{$endColumn}3");
        $this->styles->text(
            $sheet,
            'A3',
            $resultSheet->advisorProfile->programme_name.' · '.$resultSheet->studentSet->name.' · '
                .$resultSheet->academicSession->name.' Session',
        );
        $sheet->getStyle("A3:{$endColumn}3")->getFont()->setSize(10)->getColor()->setARGB(WorkbookStyler::Muted);
        $sheet->mergeCells("A4:{$endColumn}4");
        $sheet->setCellValue('A4', $this->publicationLabel($resultSheet));
        $sheet->getStyle("A4:{$endColumn}4")->getFont()->setBold(true)->getColor()->setARGB(
            $resultSheet->status === ResultSheet::StatusPublished ? '16803C' : WorkbookStyler::Danger,
        );
        $sheet->mergeCells("A5:{$endColumn}5");
        $sheet->setCellValue(
            'A5',
            sprintf(
                '%d students · %d courses · Average GPA %s · Generated %s',
                $rows->count(),
                $courses->count(),
                $rows->isEmpty() ? '0.00' : number_format((float) $rows->average('gpa'), 2),
                now()->format('d M Y, H:i'),
            ),
        );
        $sheet->getStyle("A5:{$endColumn}5")->getFont()->setSize(9)->getColor()->setARGB(WorkbookStyler::Muted);

        $superHeaderRow = 8;
        $courseHeaderRow = 9;
        $unitsHeaderRow = 10;
        $dataStartRow = 11;
        $sheet->mergeCells("A{$superHeaderRow}:A{$unitsHeaderRow}");
        $sheet->mergeCells("B{$superHeaderRow}:B{$unitsHeaderRow}");
        $sheet->mergeCells("C{$superHeaderRow}:C{$unitsHeaderRow}");
        $sheet->mergeCells("{$gpaColumn}{$superHeaderRow}:{$gpaColumn}{$unitsHeaderRow}");
        $sheet->setCellValue("A{$superHeaderRow}", 'S/N');
        $sheet->setCellValue("B{$superHeaderRow}", 'Full Name');
        $sheet->setCellValue("C{$superHeaderRow}", 'Registration Number');
        $sheet->setCellValue("{$gpaColumn}{$superHeaderRow}", 'GPA');

        if ($courses->isNotEmpty()) {
            $sheet->mergeCells("{$courseStartColumn}{$superHeaderRow}:{$courseEndColumn}{$superHeaderRow}");
            $sheet->setCellValue("{$courseStartColumn}{$superHeaderRow}", 'Courses');
        }

        $this->styles->tableHeader($sheet, "A{$superHeaderRow}:{$endColumn}{$unitsHeaderRow}");
        $sheet->getStyle("A{$superHeaderRow}:{$endColumn}{$unitsHeaderRow}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);

        foreach ($courses as $index => $course) {
            $column = Coordinate::stringFromColumnIndex($courseStartColumnIndex + $index);
            $this->styles->text($sheet, "{$column}{$courseHeaderRow}", $course->course_code);
            $sheet->setCellValue("{$column}{$unitsHeaderRow}", $course->credit_units.' units');
            $comment = $sheet->getComment("{$column}{$courseHeaderRow}");
            $comment->getText()->createTextRun($course->course_title."\n{$course->credit_units} units");
            $comment->setWidth('320pt')->setHeight('80pt');

            if ($this->isCarryoverCourse($resultSheet, $course)) {
                $sheet->getStyle("{$column}{$superHeaderRow}:{$column}{$unitsHeaderRow}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()
                    ->setARGB(WorkbookStyler::DangerFill);
                $sheet->getStyle("{$column}{$courseHeaderRow}:{$column}{$unitsHeaderRow}")->getFont()
                    ->getColor()
                    ->setARGB(WorkbookStyler::Danger);
            }
        }

        $row = $dataStartRow;

        foreach ($rows as $index => $studentResult) {
            $courseResults = $studentResult->courseResults->keyBy('result_sheet_course_id');
            $sheet->setCellValue("A{$row}", $index + 1);
            $this->styles->text($sheet, "B{$row}", $studentResult->studentProfile?->user?->name ?? 'Student');
            $this->styles->text($sheet, "C{$row}", $studentResult->studentProfile?->registration_number);

            foreach ($courses as $courseIndex => $course) {
                $column = Coordinate::stringFromColumnIndex($courseStartColumnIndex + $courseIndex);
                $courseResult = $courseResults->get($course->id);

                $this->styles->grade(
                    $sheet,
                    "{$column}{$row}",
                    $courseResult instanceof StudentCourseResult ? $courseResult->grade : null,
                );

                if ($this->isCarryoverCourse($resultSheet, $course)) {
                    $sheet->getStyle("{$column}{$row}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()
                        ->setARGB(WorkbookStyler::DangerFill);
                }
            }

            $sheet->setCellValue("{$gpaColumn}{$row}", (float) $studentResult->gpa);
            $sheet->getStyle("{$gpaColumn}{$row}")->getNumberFormat()->setFormatCode('0.00');
            $row++;
        }

        $this->styles->tableBody($sheet, $dataStartRow, $row - 1, $endColumn);

        if ($row > $dataStartRow) {
            foreach ($courses as $index => $course) {
                if (! $this->isCarryoverCourse($resultSheet, $course)) {
                    continue;
                }

                $column = Coordinate::stringFromColumnIndex($courseStartColumnIndex + $index);
                $sheet->getStyle("{$column}{$dataStartRow}:{$column}".($row - 1))->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()
                    ->setARGB(WorkbookStyler::DangerFill);
            }
        }

        if ($rows->isEmpty()) {
            $sheet->mergeCells("A{$row}:{$endColumn}".($row + 1));
            $sheet->setCellValue("A{$row}", 'No student results are available in this sheet.');
            $sheet->getStyle("A{$row}:{$endColumn}".($row + 1))->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle("A{$row}:{$endColumn}".($row + 1))->getFont()->getColor()->setARGB(WorkbookStyler::Muted);
            $row += 2;
        }

        $sheet->getColumnDimension('A')->setWidth(7);
        $sheet->getColumnDimension('B')->setWidth(30);
        $sheet->getColumnDimension('C')->setWidth(23);

        foreach (range($courseStartColumnIndex, $courseEndColumnIndex) as $columnIndex) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($columnIndex))->setWidth(13);
        }

        $sheet->getColumnDimension($gpaColumn)->setWidth(9);
        $sheet->getStyle("A{$superHeaderRow}:{$endColumn}".max($row - 1, $unitsHeaderRow))->getBorders()
            ->getOutline()
            ->setBorderStyle(Border::BORDER_THIN)
            ->getColor()
            ->setARGB(WorkbookStyler::Border);
        $sheet->freezePane("{$courseStartColumn}{$dataStartRow}");
        $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd($superHeaderRow, $unitsHeaderRow);
        $sheet->getPageSetup()->setPrintArea("A1:{$endColumn}".max($row - 1, $unitsHeaderRow));

        return $spreadsheet;
    }

    private function isCarryoverCourse(ResultSheet $resultSheet, ResultSheetCourse $course): bool
    {
        return $course->courseOffering?->academic_year_semester_id !== null
            && (int) $course->courseOffering->academic_year_semester_id !== (int) $resultSheet->academic_year_semester_id;
    }

    private function publicationLabel(ResultSheet $resultSheet): string
    {
        if ($resultSheet->status === ResultSheet::StatusPublished) {
            return 'Published Results'.($resultSheet->published_at === null ? '' : ' · Published '.$resultSheet->published_at->format('d M Y'));
        }

        return 'Not Published · '.Str::headline($resultSheet->status);
    }
}
