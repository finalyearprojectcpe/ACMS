<?php

namespace App\Services;

use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;

final class WordDocumentLayout
{
    /**
     * @var array<string, int|string>
     */
    public const TABLE_STYLE = [
        'cellMarginTop' => 90,
        'cellMarginBottom' => 90,
        'cellMarginLeft' => 55,
        'cellMarginRight' => 55,
        'borderInsideHSize' => 2,
        'borderInsideHColor' => '000000',
    ];

    /** @var array<string, int|string> */
    public const HEADER_CELL_STYLE = ['borderBottomSize' => 6, 'borderBottomColor' => '000000'];

    /** @var array<string, int|string> */
    public const TOTAL_CELL_STYLE = ['borderTopSize' => 6, 'borderTopColor' => '000000'];

    public function addSection(PhpWord $word, string $title): Section
    {
        $word->setDefaultFontName('Arial');
        $word->setDefaultFontSize(10);
        $word->getDocInfo()->setTitle($title);

        return $word->addSection([
            'marginTop' => 1100,
            'marginBottom' => 1100,
            'marginLeft' => 1100,
            'marginRight' => 1100,
        ]);
    }

    public function addTitle(Section $section, string $title): void
    {
        $section->addText($title, ['bold' => true, 'size' => 16], [
            'spaceAfter' => 220,
            'keepNext' => true,
        ]);
    }

    /** @param list<array{label: string, value: string}> $fields */
    public function addMetadata(Section $section, array $fields): void
    {
        $table = $section->addTable(['cellMargin' => 0]);

        foreach (array_chunk($fields, 2) as $rowFields) {
            $table->addRow(null, ['cantSplit' => true]);

            foreach ($rowFields as $field) {
                $cell = $table->addCell(4600);
                $cell->addText(mb_strtoupper($field['label']), ['bold' => true, 'size' => 8], ['spaceAfter' => 35]);
                $cell->addText($field['value'], ['size' => 10], ['spaceAfter' => 130]);
            }

            if (count($rowFields) === 1) {
                $table->addCell(4600);
            }
        }
    }

    public function save(PhpWord $word, string $path): void
    {
        $reporting = error_reporting();
        $escaping = Settings::isOutputEscapingEnabled();

        try {
            // PHPWord 1.4 looks up a null style name for every paragraph on PHP 8.5.
            error_reporting($reporting & ~E_DEPRECATED);
            Settings::setOutputEscapingEnabled(true);
            IOFactory::createWriter($word, 'Word2007')->save($path);
        } finally {
            Settings::setOutputEscapingEnabled($escaping);
            error_reporting($reporting);
        }
    }
}
