<?php

namespace App\Services;

use App\Grade;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SetResultsSpreadsheet
{
    /** @param array<string, mixed> $report */
    public function build(array $report): Spreadsheet
    {
        $book = new Spreadsheet;
        $book->getProperties()->setTitle('Set Results for '.$report['set']);
        $book->getDefaultStyle()->getFont()->setName('Tahoma')->setSize(11);
        $previous = null;

        foreach ($report['groups'] as $index => $group) {
            $sheet = $index === 0 ? $book->getActiveSheet() : $book->createSheet();
            $sheet->setTitle($this->sheetName($group));
            $this->writeSheet($sheet, $report, $group, $previous);
            $previous = [
                'name' => $sheet->getTitle(),
                'lastRow' => max(8, count($report['students']) + 7),
                'points' => Coordinate::stringFromColumnIndex(count($group['columns']) + 10),
                'units' => Coordinate::stringFromColumnIndex(count($group['columns']) + 11),
            ];
        }

        $book->setActiveSheetIndex(0);

        return $book;
    }

    /** @param array<string, mixed> $group */
    private function sheetName(array $group): string
    {
        $level = preg_replace('/\s*Level$/i', 'L', $group['level']);
        $semester = $group['semester'] === '1st Semester' ? '1st' : '2nd';

        return substr($level.' '.$semester.' '.str_replace('/', '', $group['session']), 0, 31);
    }

    /**
     * @param  array<string, mixed>  $report
     * @param  array<string, mixed>  $group
     * @param  array<string, mixed>|null  $previous
     */
    private function writeSheet(Worksheet $sheet, array $report, array $group, ?array $previous): void
    {
        $sheet->setShowGridlines(false);
        $courseCount = count($group['columns']);
        $firstSummary = $courseCount + 4;
        $lastCourse = $firstSummary - 1;
        $remarksColumn = $firstSummary + 9;
        $last = Coordinate::stringFromColumnIndex($remarksColumn);
        $lastStudentRow = count($report['students']) + 7;

        $sheet->mergeCells('A1:C1');
        $sheet->setCellValue('A1', $report['set'].' Results');
        $sheet->mergeCells('A2:C2');
        $sheet->setCellValue('A2', $group['level'].'  ·  '.$group['semester'].'  ·  '.$group['session'].' Session');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A2')->getFont()->setSize(10);
        $sheet->getRowDimension(1)->setRowHeight(27);
        $sheet->getRowDimension(2)->setRowHeight(22);
        $sheet->getRowDimension(3)->setRowHeight(12);
        $sheet->getColumnDimension('A')->setWidth(7);
        $sheet->getColumnDimension('B')->setWidth(19);
        $sheet->getColumnDimension('C')->setWidth(32);

        foreach (['S/N', 'Registration No.', 'Student'] as $index => $label) {
            $column = Coordinate::stringFromColumnIndex($index + 1);
            $sheet->mergeCells($column.'4:'.$column.'7');
            $sheet->setCellValue($column.'4', $label);
        }

        $courseEnd = Coordinate::stringFromColumnIndex($lastCourse);
        $sheet->mergeCells('D4:'.$courseEnd.'4');
        $sheet->setCellValue('D4', 'Courses and Units');
        foreach ($group['columns'] as $index => $course) {
            $column = Coordinate::stringFromColumnIndex($index + 4);
            $code = preg_split('/\s+/', trim($course['code']), 2);
            $sheet->setCellValueExplicit($column.'5', $code[0], DataType::TYPE_STRING);
            $sheet->setCellValueExplicit($column.'6', $code[1] ?? '', DataType::TYPE_STRING);
            $sheet->setCellValue($column.'7', $course['units']);
            $sheet->getColumnDimension($column)->setWidth(max(9, min(15, strlen($code[0]) + 2)));
            $sheet->getComment($column.'5')->getText()->createTextRun($course['title']);
        }

        $summaryColumns = [];
        foreach (['Current', 'Previous', 'Cumulative'] as $groupIndex => $heading) {
            $start = $firstSummary + $groupIndex * 3;
            $end = $start + 2;
            $startLetter = Coordinate::stringFromColumnIndex($start);
            $endLetter = Coordinate::stringFromColumnIndex($end);
            $sheet->mergeCells($startLetter.'4:'.$endLetter.'4');
            $sheet->setCellValue($startLetter.'4', $heading);
            foreach (['TGP', 'TNU', 'GPA'] as $offset => $metric) {
                $letter = Coordinate::stringFromColumnIndex($start + $offset);
                $sheet->mergeCells($letter.'5:'.$letter.'7');
                $sheet->setCellValue($letter.'5', $metric);
                $sheet->getColumnDimension($letter)->setWidth($metric === 'GPA' ? 9 : 8);
                $summaryColumns[$heading][$metric] = $letter;
            }
        }
        $sheet->mergeCells($last.'4:'.$last.'7');
        $sheet->setCellValue($last.'4', 'Remarks');
        $sheet->getColumnDimension($last)->setWidth(11);
        $sheet->getRowDimension(4)->setRowHeight(23);
        $sheet->getRowDimension(5)->setRowHeight(19);
        $sheet->getRowDimension(6)->setRowHeight(19);
        $sheet->getRowDimension(7)->setRowHeight(19);
        $sheet->getStyle('A4:'.$last.'7')->getFont()->setBold(true)->setSize(10);
        $sheet->getStyle('A4:'.$last.'7')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle('D4:'.$last.'7')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A7:'.$last.'7')->getBorders()->getBottom()->setBorderStyle(Border::BORDER_MEDIUM);

        foreach ($report['students'] as $index => $student) {
            $row = $index + 8;
            $period = $student['periods'][$group['id']];
            $sheet->setCellValue('A'.$row, $index + 1);
            $sheet->setCellValueExplicit('B'.$row, $student['registrationNumber'], DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('C'.$row, $student['name'], DataType::TYPE_STRING);
            $points = [];
            $units = [];
            $validity = [];

            foreach ($group['columns'] as $courseIndex => $course) {
                $column = Coordinate::stringFromColumnIndex($courseIndex + 4);
                $reference = $column.$row;
                $cell = $period['cells'][$courseIndex];
                $value = $period['tones'][$courseIndex] === 'carryover' && $cell !== '—' ? $cell.' CO' : $cell;
                $sheet->setCellValueExplicit($reference, $value, DataType::TYPE_STRING);
                $grade = $this->gradeCondition($reference);
                $points[] = 'IF('.$grade.','.$this->gradePoints($reference).'*'.$course['units'].',0)';
                $units[] = 'IF('.$grade.','.$course['units'].',0)';
                $validity[] = 'OR('.$reference.'="—",'.$grade.')';
            }

            $current = $summaryColumns['Current'];
            $prior = $summaryColumns['Previous'];
            $cumulative = $summaryColumns['Cumulative'];
            $sheet->setCellValue($current['TGP'].$row, '=SUM('.implode(',', $points).')');
            $sheet->setCellValue($current['TNU'].$row, '=SUM('.implode(',', $units).')');
            $sheet->setCellValue($current['GPA'].$row, '=IF(AND('.$current['TNU'].$row.'>0,'.implode(',', $validity).'),ROUND('.$current['TGP'].$row.'/'.$current['TNU'].$row.',2),"")');

            if ($previous !== null) {
                foreach (['TGP' => 'points', 'TNU' => 'units'] as $metric => $source) {
                    $sheet->setCellValue($prior[$metric].$row, '='.$this->previousValue($previous, $source, $row));
                }
            } else {
                foreach (['TGP' => 'points', 'TNU' => 'units'] as $metric => $source) {
                    $sheet->setCellValue($prior[$metric].$row, $student['starting']['blocked'] ? null : $student['starting'][$source]);
                }
            }

            $sheet->setCellValue($prior['GPA'].$row, '=IF('.$prior['TNU'].$row.'>0,ROUND('.$prior['TGP'].$row.'/'.$prior['TNU'].$row.',2),"")');
            $hasPending = 'AND(COUNTIF(D'.$row.':'.$courseEnd.$row.',"<>—")>0,'.$current['GPA'].$row.'="")';
            $priorUnavailable = 'OR('.$prior['TGP'].$row.'="",'.$prior['TNU'].$row.'="")';
            foreach (['TGP', 'TNU'] as $metric) {
                $sheet->setCellValue($cumulative[$metric].$row, '=IF(OR('.$hasPending.','.$priorUnavailable.'),"",'.$prior[$metric].$row.'+'.$current[$metric].$row.')');
            }
            $sheet->setCellValue($cumulative['GPA'].$row, '=IF('.$cumulative['TNU'].$row.'>0,ROUND('.$cumulative['TGP'].$row.'/'.$cumulative['TNU'].$row.',2),"")');
            $failures = 'COUNTIF(D'.$row.':'.$courseEnd.$row.',"F*")';
            $sheet->setCellValue($last.$row, '=IF('.$failures.'>0,'.$failures.'&"F",IF(AND('.$current['TNU'].$row.'>0,'.implode(',', $validity).'),"Pass",""))');
        }

        if ($lastStudentRow >= 8) {
            $studentBorders = $sheet->getStyle('A8:'.$last.$lastStudentRow)->getBorders();
            $studentBorders->getHorizontal()->setBorderStyle(Border::BORDER_HAIR);
            $studentBorders->getBottom()->setBorderStyle(Border::BORDER_HAIR);
            $sheet->getStyle('D8:'.$last.$lastStudentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('A8:B'.$lastStudentRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('C8:C'.$lastStudentRow)->getAlignment()->setIndent(1);
            $sheet->getStyle('B8:B'.$lastStudentRow)->getNumberFormat()->setFormatCode('@');
            foreach (['Current', 'Previous', 'Cumulative'] as $heading) {
                $column = $summaryColumns[$heading]['GPA'];
                $sheet->getStyle($column.'8:'.$column.$lastStudentRow)->getNumberFormat()->setFormatCode('0.00');
            }
        }
        $sheet->getStyle('B4:B'.max(7, $lastStudentRow))->getBorders()->getRight()->setBorderStyle(Border::BORDER_HAIR);
        $sheet->getStyle('C4:C'.max(7, $lastStudentRow))->getBorders()->getRight()->setBorderStyle(Border::BORDER_MEDIUM);
        foreach ([$courseEnd, $summaryColumns['Current']['GPA'], $summaryColumns['Previous']['GPA'], $summaryColumns['Cumulative']['GPA']] as $boundary) {
            $sheet->getStyle($boundary.'4:'.$boundary.max(7, $lastStudentRow))->getBorders()->getRight()->setBorderStyle(Border::BORDER_MEDIUM);
        }
        $sheet->freezePane('D8');
    }

    /** @param array<string, mixed> $previous */
    private function previousValue(array $previous, string $source, int $row): string
    {
        $name = str_replace("'", "''", $previous['name']);
        $column = $previous[$source];

        return 'IFERROR(INDEX(\''.$name.'\'!$'.$column.'$8:$'.$column.'$'.$previous['lastRow'].',MATCH($B'.$row.',\''.$name.'\'!$B$8:$B$'.$previous['lastRow'].',0)),"")';
    }

    private function gradeCondition(string $reference): string
    {
        return 'OR('.implode(',', array_map(fn (Grade $grade): string => 'LEFT('.$reference.',1)="'.$grade->value.'"', Grade::cases())).')';
    }

    private function gradePoints(string $reference): string
    {
        $expression = '0';
        foreach (array_reverse(Grade::cases()) as $grade) {
            $expression = 'IF(LEFT('.$reference.',1)="'.$grade->value.'",'.$grade->points().','.$expression.')';
        }

        return $expression;
    }
}
