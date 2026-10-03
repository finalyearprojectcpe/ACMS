<?php

namespace App\Services;

use PhpOffice\PhpWord\PhpWord;

class ResultStatementWord
{
    public function __construct(private readonly WordDocumentLayout $layout) {}

    /** @param array<string, mixed> $statement */
    public function save(array $statement, string $path): void
    {
        $word = new PhpWord;
        $student = $statement['student'];
        $title = 'Statement of Results for '.$student['name'];
        $section = $this->layout->addSection($word, $title);
        $this->layout->addTitle($section, $title);
        $this->layout->addMetadata($section, [
            ['label' => 'Registration number', 'value' => $student['registrationNumber']],
            ['label' => 'Student set', 'value' => $student['set']],
            ['label' => 'Course adviser', 'value' => $student['adviser']],
            ['label' => 'As of', 'value' => $statement['asOf']],
        ]);

        foreach ($statement['periods'] as $period) {
            $section->addText(
                $period['level'].' · '.$period['semester'],
                ['bold' => true, 'size' => 11],
                ['spaceBefore' => 280, 'spaceAfter' => 35, 'keepNext' => true],
            );
            $section->addText($period['session'], ['size' => 9], ['spaceAfter' => 95, 'keepNext' => true]);

            $table = $section->addTable(WordDocumentLayout::TABLE_STYLE);
            $table->addRow(null, ['tblHeader' => true, 'cantSplit' => true]);
            foreach ([['Course', 1450], ['Course title', 4850], ['Units', 1100], ['Grade', 1600]] as [$heading, $width]) {
                $alignment = in_array($heading, ['Units', 'Grade'], true) ? 'right' : 'left';
                $table->addCell($width, WordDocumentLayout::HEADER_CELL_STYLE)
                    ->addText($heading, ['bold' => true, 'size' => 9], ['alignment' => $alignment]);
            }

            foreach ($period['courses'] as $course) {
                $table->addRow(null, ['cantSplit' => true]);
                $table->addCell(1450)->addText($course['code']);
                $table->addCell(4850)->addText($course['title']);
                $table->addCell(1100)->addText((string) $course['units'], [], ['alignment' => 'right']);
                $table->addCell(1600)->addText($course['grade'], [], ['alignment' => 'right']);
            }

            $table->addRow(null, ['cantSplit' => true]);
            $table->addCell(6300, ['gridSpan' => 2, ...WordDocumentLayout::TOTAL_CELL_STYLE])
                ->addText('Total Units', ['bold' => true]);
            $table->addCell(1100, WordDocumentLayout::TOTAL_CELL_STYLE)
                ->addText((string) $period['totalUnits'], ['bold' => true], ['alignment' => 'right']);
            $table->addCell(1600, WordDocumentLayout::TOTAL_CELL_STYLE);

            $gpa = $period['gpa'] === null ? 'Not available' : number_format($period['gpa'], 2);
            $section->addText('Semester GPA  '.$gpa, ['bold' => true], [
                'alignment' => 'right',
                'spaceBefore' => 95,
            ]);
        }

        $cgpa = $statement['cgpa'] === null ? 'Not available' : number_format($statement['cgpa'], 2);
        $section->addText('Cumulative GPA  '.$cgpa, ['bold' => true, 'size' => 11], [
            'alignment' => 'right',
            'spaceBefore' => 250,
        ]);

        $this->layout->save($word, $path);
    }
}
