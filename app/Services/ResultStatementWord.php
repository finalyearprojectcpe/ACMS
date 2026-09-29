<?php

namespace App\Services;

use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

class ResultStatementWord
{
    /** @param array<string, mixed> $statement */
    public function save(array $statement, string $path): void
    {
        $word = new PhpWord;
        $word->setDefaultFontName('Aptos');
        $word->setDefaultFontSize(10);
        $word->getDocInfo()->setTitle('Student Results to Date');
        $section = $word->addSection([
            'marginTop' => 1050,
            'marginBottom' => 1050,
            'marginLeft' => 1100,
            'marginRight' => 1100,
        ]);

        $section->addText('STUDENT RESULTS TO DATE', ['bold' => true, 'size' => 15], ['spaceAfter' => 100]);
        $section->addText('Editable result statement | Not an official transcript', ['color' => '64748B', 'size' => 9], ['spaceAfter' => 260]);
        $student = $statement['student'];
        $section->addText('Student: '.$student['name'], ['bold' => true]);
        $section->addText('Registration number: '.$student['registrationNumber']);
        $section->addText('Entry set: '.$student['set']);
        $section->addText('Course adviser: '.$student['adviser']);
        $section->addText('As of: '.$statement['asOf'], ['color' => '64748B'], ['spaceAfter' => 260]);

        foreach ($statement['periods'] as $period) {
            $section->addText(
                $period['level'].' | '.$period['semester'].' | '.$period['session'],
                ['bold' => true, 'size' => 11],
                ['spaceBefore' => 160, 'spaceAfter' => 90, 'keepNext' => true],
            );

            $table = $section->addTable([
                'borderSize' => 5,
                'borderColor' => 'CBD5E1',
                'cellMargin' => 80,
            ]);
            $table->addRow(null, ['tblHeader' => true]);
            foreach ([['Course', 1550], ['Course title', 5050], ['Units', 900], ['Grade', 1450]] as [$heading, $width]) {
                $table->addCell($width, ['bgColor' => 'E8EDF1'])
                    ->addText($heading, ['bold' => true, 'size' => 9]);
            }

            foreach ($period['courses'] as $course) {
                $table->addRow();
                $table->addCell(1550)->addText($course['code']);
                $table->addCell(5050)->addText($course['title']);
                $table->addCell(900)->addText((string) $course['units']);
                $table->addCell(1450)->addText($course['grade']);
            }

            $table->addRow();
            $table->addCell(1550, ['bgColor' => 'F2F5F7'])->addText('Published units', ['bold' => true]);
            $table->addCell(5050, ['bgColor' => 'F2F5F7'])->addText('');
            $table->addCell(900, ['bgColor' => 'F2F5F7'])->addText((string) $period['publishedUnits'], ['bold' => true]);
            $table->addCell(1450, ['bgColor' => 'F2F5F7'])->addText('');

            $gpa = $period['gpa'] === null ? 'Not available' : number_format($period['gpa'], 2);
            $section->addText('Semester GPA: '.$gpa, ['bold' => true], ['spaceBefore' => 80]);
            if ($period['awaitingPublication'] > 0) {
                $section->addText($period['awaitingPublication'].' course result(s) awaiting publication.', ['color' => '64748B', 'size' => 9]);
            }
        }

        $cgpa = $statement['cgpa'] === null ? 'Not available' : number_format($statement['cgpa'], 2);
        $section->addText('Current official CGPA: '.$cgpa, ['bold' => true, 'size' => 11], ['spaceBefore' => 220]);

        IOFactory::createWriter($word, 'Word2007')->save($path);
    }
}
