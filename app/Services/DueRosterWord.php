<?php

namespace App\Services;

use App\Models\Due;
use App\Models\StudentDueAssignment;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\PhpWord;

class DueRosterWord
{
    public function __construct(
        private readonly CurrencyFormatter $currency,
        private readonly WordDocumentLayout $layout,
    ) {}

    public function save(Due $due, string $path, string $status): void
    {
        $due->loadMissing([
            'studentSet',
            'academicSession',
            'academicSessionSemester.semester',
            'assignments.studentProfile',
            'assignments.latestPayment',
        ]);
        $assignments = $due->assignments
            ->sortBy(fn (StudentDueAssignment $assignment): string => mb_strtolower($assignment->studentProfile->official_name))
            ->values();
        $paid = $assignments->where('status', StudentDueAssignment::StatusPaid);
        $unpaid = $assignments->where('status', StudentDueAssignment::StatusPending);
        $waived = $assignments->where('status', StudentDueAssignment::StatusWaived);

        $title = match ($status) {
            'paid' => 'Paid Students for '.$due->title,
            'unpaid' => 'Unpaid Students for '.$due->title,
            'waived' => 'Waived Students for '.$due->title,
            default => $due->title.' Payment List',
        };
        $word = new PhpWord;
        $section = $this->layout->addSection($word, $title);
        $this->layout->addTitle($section, $title);
        $this->layout->addMetadata($section, [
            ['label' => 'Student set', 'value' => $due->studentSet->label()],
            ['label' => 'Academic period', 'value' => $due->academicSession->label().' Session · '
                .($due->academicSessionSemester?->semester->name ?? 'Whole session')],
            ['label' => 'Due amount', 'value' => $this->currency->nairaFromKobo((int) $due->amount_kobo)],
            ['label' => 'As of', 'value' => now()->format('j F Y')],
        ]);

        if ($status === 'all' || $status === 'paid') {
            $this->addGroup($section, $status === 'all' ? 'Paid' : null, ['Student name', 'Registration number', 'Amount paid'], $paid
                ->map(fn (StudentDueAssignment $assignment): array => [
                    $assignment->studentProfile->official_name,
                    $assignment->studentProfile->registration_number,
                    $this->paidAmount($assignment),
                ])->all());
        }

        if ($status === 'all' || $status === 'unpaid') {
            $this->addGroup($section, $status === 'all' ? 'Unpaid' : null, ['Student name', 'Registration number', 'Amount due'], $unpaid
                ->map(fn (StudentDueAssignment $assignment): array => [
                    $assignment->studentProfile->official_name,
                    $assignment->studentProfile->registration_number,
                    $this->currency->nairaFromKobo((int) $assignment->amount_kobo),
                ])->all());
        }

        if ($status === 'all' || $status === 'waived') {
            $this->addGroup($section, $status === 'all' ? 'Waived' : null, ['Student name', 'Registration number'], $waived
                ->map(fn (StudentDueAssignment $assignment): array => [
                    $assignment->studentProfile->official_name,
                    $assignment->studentProfile->registration_number,
                ])->all());
        }

        $this->layout->save($word, $path);
    }

    private function paidAmount(StudentDueAssignment $assignment): string
    {
        return $assignment->latestPayment === null
            ? 'Not recorded'
            : $this->currency->nairaFromKobo((int) $assignment->latestPayment->amount_kobo);
    }

    /**
     * @param  list<string>  $headings
     * @param  array<int, list<string>>  $rows
     */
    private function addGroup(Section $section, ?string $title, array $headings, array $rows): void
    {
        if ($title !== null) {
            $section->addText($title, ['bold' => true, 'size' => 11], [
                'spaceBefore' => 260,
                'spaceAfter' => 100,
                'keepNext' => true,
            ]);
        }

        if ($rows === []) {
            $section->addText('No students.', [], ['spaceBefore' => 100]);

            return;
        }

        $headings = ['S/N', ...$headings];
        $widths = match (count($headings)) {
            4 => [600, 3800, 2600, 2000],
            default => [600, 5100, 3300],
        };
        $table = $section->addTable(WordDocumentLayout::TABLE_STYLE);
        $table->addRow(null, ['tblHeader' => true, 'cantSplit' => true]);
        foreach ($headings as $index => $heading) {
            $table->addCell($widths[$index], WordDocumentLayout::HEADER_CELL_STYLE)
                ->addText($heading, ['bold' => true, 'size' => 9], [
                    'alignment' => $index === 0 ? 'center' : (count($headings) === 4 && $index === 3 ? 'right' : 'left'),
                ]);
        }

        foreach (array_values($rows) as $index => $row) {
            $table->addRow(null, ['cantSplit' => true]);
            foreach ([(string) ($index + 1), ...$row] as $columnIndex => $value) {
                $table->addCell($widths[$columnIndex])
                    ->addText($value, [], [
                        'alignment' => $columnIndex === 0 ? 'center' : (count($headings) === 4 && $columnIndex === 3 ? 'right' : 'left'),
                    ]);
            }
        }
    }
}
