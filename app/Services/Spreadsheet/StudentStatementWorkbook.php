<?php

namespace App\Services\Spreadsheet;

use App\Models\AdvisorProfile;
use App\Models\ResultSheet;
use App\Models\StudentCourseResult;
use App\Models\StudentProfile;
use App\Models\StudentSemesterResult;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StudentStatementWorkbook
{
    public function __construct(private readonly WorkbookStyler $styles) {}

    public function build(StudentProfile $studentProfile, AdvisorProfile $advisor): Spreadsheet
    {
        $studentProfile->loadMissing(['user', 'currentLevel', 'studentSet.entryAcademicSession', 'advisorProfile']);
        $semesterResults = $this->publishedSemesterResults($studentProfile, $advisor);
        $hasRetakes = $semesterResults->contains(
            fn (StudentSemesterResult $result): bool => $result->courseResults->contains(
                fn (StudentCourseResult $courseResult): bool => $this->isRetake($courseResult, $result->resultSheet),
            ),
        );
        $endColumn = $hasRetakes ? 'G' : 'F';
        $title = 'Statement of Results - '.$studentProfile->registration_number;
        $spreadsheet = $this->styles->workbook($title, 'Approved academic results for '.$studentProfile->user->name);
        $sheet = $spreadsheet->getActiveSheet();

        $this->styles->prepareSheet($sheet, 'Statement');
        $this->writeHeading($sheet, $advisor, $endColumn);
        $this->writeStudentSummary($sheet, $studentProfile, $advisor, $semesterResults, $endColumn);

        if ($semesterResults->isEmpty()) {
            $sheet->mergeCells("A11:{$endColumn}13");
            $sheet->setCellValue('A11', 'No published results are available for this student yet.');
            $sheet->getStyle("A11:{$endColumn}13")->applyFromArray([
                'font' => ['color' => ['argb' => WorkbookStyler::Muted]],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ]);
            $sheet->getPageSetup()->setPrintArea("A1:{$endColumn}13");

            return $spreadsheet;
        }

        $row = 11;
        $cumulativeUnits = 0;
        $cumulativeGradePoints = 0;

        foreach ($semesterResults as $semesterResult) {
            $resultSheet = $semesterResult->resultSheet;
            $level = $resultSheet->academicYearSemester->academicYear;
            $semester = $resultSheet->academicYearSemester->semester;
            $sectionLabel = $this->styles->qualifiedSemester($level->name, $semester->name)
                .' · '.$resultSheet->academicSession->name.' Session';

            $sheet->mergeCells("A{$row}:{$endColumn}{$row}");
            $this->styles->text($sheet, "A{$row}", $sectionLabel);
            $this->styles->sectionHeader($sheet, "A{$row}:{$endColumn}{$row}");
            $sheet->getRowDimension($row)->setRowHeight(24);
            $row++;

            $headings = ['Course Code', 'Course Title', 'Units', 'Grade', 'Grade Point', 'Quality Points'];

            if ($hasRetakes) {
                $headings[] = 'Attempt';
            }

            foreach ($headings as $index => $heading) {
                $sheet->setCellValue([$index + 1, $row], $heading);
            }

            $this->styles->tableHeader($sheet, "A{$row}:{$endColumn}{$row}");
            $sheet->getRowDimension($row)->setRowHeight(27);
            $row++;
            $courseStartRow = $row;

            foreach ($semesterResult->courseResults->sortBy(
                fn (StudentCourseResult $result): int => (int) $result->resultSheetCourse?->sort_order,
            ) as $courseResult) {
                $course = $courseResult->resultSheetCourse;

                $this->styles->text($sheet, "A{$row}", $course?->course_code);
                $this->styles->text($sheet, "B{$row}", $course?->course_title);
                $sheet->setCellValue("C{$row}", $course?->credit_units);
                $this->styles->grade($sheet, "D{$row}", $courseResult->grade);
                $sheet->setCellValue("E{$row}", $courseResult->grade_point);
                $sheet->setCellValue("F{$row}", $courseResult->quality_points);

                if ($hasRetakes) {
                    $this->styles->text(
                        $sheet,
                        "G{$row}",
                        $this->isRetake($courseResult, $resultSheet) ? 'Retake' : null,
                    );
                }

                $row++;
            }

            $this->styles->tableBody($sheet, $courseStartRow, $row - 1, $endColumn);

            if ($row > $courseStartRow) {
                $sheet->getStyle("B{$courseStartRow}:B".($row - 1))->getAlignment()->setWrapText(true);
            }
            $cumulativeUnits += (int) $semesterResult->total_units;
            $cumulativeGradePoints += (int) $semesterResult->total_grade_points;
            $cumulativeCgpa = $cumulativeUnits === 0 ? 0.0 : $cumulativeGradePoints / $cumulativeUnits;

            $sheet->mergeCells("A{$row}:B{$row}");
            $sheet->setCellValue("A{$row}", 'Semester Summary');
            $sheet->setCellValue("C{$row}", 'Total Units: '.(int) $semesterResult->total_units);
            $sheet->setCellValue("D{$row}", 'GPA: '.number_format((float) $semesterResult->gpa, 2));
            $sheet->mergeCells("E{$row}:{$endColumn}{$row}");
            $sheet->setCellValue("E{$row}", 'CGPA: '.number_format($cumulativeCgpa, 2));
            $this->styles->summaryRow($sheet, "A{$row}:{$endColumn}{$row}");
            $sheet->getRowDimension($row)->setRowHeight(23);
            $row += 2;
        }

        $this->styles->columnWidths($sheet, [
            'A' => 16,
            'B' => 42,
            'C' => 9,
            'D' => 9,
            'E' => 13,
            'F' => 15,
            ...($hasRetakes ? ['G' => 12] : []),
        ]);
        $sheet->getPageSetup()->setPrintArea("A1:{$endColumn}".($row - 2));

        return $spreadsheet;
    }

    /**
     * @return Collection<int, StudentSemesterResult>
     */
    private function publishedSemesterResults(StudentProfile $studentProfile, AdvisorProfile $advisor): Collection
    {
        return StudentSemesterResult::query()
            ->with([
                'resultSheet.academicSession',
                'resultSheet.studentSet.entryAcademicSession',
                'resultSheet.academicYearSemester.academicYear',
                'resultSheet.academicYearSemester.semester',
                'courseResults.resultSheetCourse.courseOffering.academicYearSemester',
            ])
            ->where('student_profile_id', $studentProfile->id)
            ->whereHas('resultSheet', fn ($query) => $query
                ->where('advisor_profile_id', $advisor->id)
                ->where('status', ResultSheet::StatusPublished))
            ->get()
            ->sortBy(fn (StudentSemesterResult $result): string => sprintf(
                '%s-%03d-%03d',
                $result->resultSheet?->academicSession?->starts_on?->toDateString() ?? '',
                $result->resultSheet?->academicYearSemester?->academic_year_id ?? 0,
                $result->resultSheet?->academicYearSemester?->semester?->sort_order ?? 0,
            ))
            ->values();
    }

    private function writeHeading(Worksheet $sheet, AdvisorProfile $advisor, string $endColumn): void
    {
        $institutionName = $advisor->institution_name ?: config('app.name');

        $sheet->mergeCells("A1:{$endColumn}1");
        $this->styles->text($sheet, 'A1', $institutionName);
        $this->styles->documentSubtitle($sheet, "A1:{$endColumn}1");
        $sheet->mergeCells("A2:{$endColumn}2");
        $sheet->setCellValue('A2', 'Statement of Results');
        $this->styles->documentTitle($sheet, "A2:{$endColumn}2");
        $sheet->mergeCells("A3:{$endColumn}3");
        $sheet->setCellValue('A3', 'Generated '.now()->format('d M Y, H:i'));
        $sheet->getStyle("A3:{$endColumn}3")->getFont()->setSize(9)->getColor()->setARGB(WorkbookStyler::Muted);
        $sheet->getRowDimension(2)->setRowHeight(28);
    }

    /**
     * @param  Collection<int, StudentSemesterResult>  $semesterResults
     */
    private function writeStudentSummary(
        Worksheet $sheet,
        StudentProfile $studentProfile,
        AdvisorProfile $advisor,
        Collection $semesterResults,
        string $endColumn,
    ): void {
        $totalUnits = (int) $semesterResults->sum('total_units');
        $totalGradePoints = (int) $semesterResults->sum('total_grade_points');
        $cgpa = $totalUnits === 0 ? null : number_format($totalGradePoints / $totalUnits, 2);
        $level = $studentProfile->currentLevel;
        $standing = $level === null
            ? 'Not set'
            : ((int) $level->year_number > (int) $studentProfile->studentSet->minimum_years_to_degree
                ? 'Graduated'
                : $this->styles->shortLevel($level->name));

        $this->writeMetadataPair($sheet, 5, 'Student', $studentProfile->user->name, 'Registration Number', $studentProfile->registration_number, $endColumn);
        $this->writeMetadataPair($sheet, 6, 'Institution', $advisor->institution_name ?: 'Not set', 'Programme', $advisor->programme_name, $endColumn);
        $this->writeMetadataPair($sheet, 7, 'Student Set', $studentProfile->studentSet?->name ?? 'Not set', 'Current Standing', $standing, $endColumn);
        $this->writeMetadataPair($sheet, 8, 'Overall CGPA', $cgpa ?? 'Not available', 'Published Units', (string) $totalUnits, $endColumn);
        $sheet->getStyle("A5:{$endColumn}8")->getAlignment()->setWrapText(true);
        $sheet->getRowDimension(5)->setRowHeight(28);
        $sheet->getRowDimension(6)->setRowHeight(32);
        $sheet->getRowDimension(7)->setRowHeight(28);
        $sheet->getRowDimension(8)->setRowHeight(28);
    }

    private function writeMetadataPair(
        Worksheet $sheet,
        int $row,
        string $leftLabel,
        string $leftValue,
        string $rightLabel,
        string $rightValue,
        string $endColumn,
    ): void {
        $sheet->mergeCells("B{$row}:C{$row}");
        $sheet->mergeCells("E{$row}:{$endColumn}{$row}");
        $sheet->setCellValue("A{$row}", $leftLabel);
        $this->styles->text($sheet, "B{$row}", $leftValue);
        $sheet->setCellValue("D{$row}", $rightLabel);
        $this->styles->text($sheet, "E{$row}", $rightValue);

        $this->styles->metadataLabel($sheet, "A{$row}");
        $this->styles->metadataLabel($sheet, "D{$row}");
        $this->styles->metadataValue($sheet, "B{$row}:C{$row}");
        $this->styles->metadataValue($sheet, "E{$row}:{$endColumn}{$row}");
    }

    private function isRetake(StudentCourseResult $courseResult, ?ResultSheet $resultSheet): bool
    {
        $offeringYearSemesterId = $courseResult->resultSheetCourse?->courseOffering?->academic_year_semester_id;

        return $offeringYearSemesterId !== null
            && $resultSheet !== null
            && (int) $offeringYearSemesterId !== (int) $resultSheet->academic_year_semester_id;
    }
}
