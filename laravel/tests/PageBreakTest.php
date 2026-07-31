<?php

use Peppermint\DocumentBuilder\Data\DocumentData;
use Peppermint\DocumentBuilder\DocumentBuilder;
use Peppermint\DocumentBuilder\Presets\Din5008Preset;
use Peppermint\DocumentBuilder\Renderers\DomPdfRenderer;
use Peppermint\DocumentBuilder\Services\LineItemsRenderer;
use Peppermint\DocumentBuilder\Services\PlaceholderRenderer;
use Peppermint\DocumentBuilder\Services\TotalsRenderer;

/**
 * Locks the page-break guarantees in. These are the failures that only show up
 * once a document grows past one sheet, which is exactly when nobody is looking.
 */
function longOffer(int $count): DocumentData
{
    $items = [];
    $net = 0.0;

    for ($i = 1; $i <= $count; $i++) {
        $price = 12.5 * $i;
        $net += $price;
        $items[] = [
            'position' => (string) $i,
            'description' => "Digitaldruck Bogenoffset, Position {$i}",
            'note' => '135 g/m² Bilderdruck matt',
            'quantity' => 1,
            'unit' => 'Stk',
            'unit_price' => $price,
            'total' => $price,
        ];
    }

    return DocumentData::fromArray([
        'type' => 'offer',
        'subject' => 'Angebot',
        'sender' => ['name' => 'Peppermint Digital GmbH', 'lines' => ['Musterstraße 1', '26123 Oldenburg']],
        'recipient' => ['name' => 'Mustermann GmbH', 'lines' => ['Beispielweg 42', '26123 Oldenburg']],
        'meta' => ['Angebotsnummer' => 'AN-2026-0815'],
        'line_items' => $items,
        'totals' => [
            'net' => $net,
            'gross' => $net * 1.19,
            'taxes' => [['label' => 'zzgl. 19 % USt.', 'amount' => $net * 0.19]],
        ],
    ]);
}

/**
 * @return array{pages: int, last: string}
 */
function renderPages(int $count): array
{
    $builder = new DocumentBuilder(
        new Din5008Preset,
        new DomPdfRenderer,
        new PlaceholderRenderer,
        new LineItemsRenderer,
        new TotalsRenderer,
    );

    $file = tempnam(sys_get_temp_dir(), 'db-break-').'.pdf';
    file_put_contents($file, $builder->pdf(longOffer($count), '{{ line_items }}{{ totals }}'));

    $pages = (int) trim((string) shell_exec('pdfinfo '.escapeshellarg($file)." | awk '/^Pages/{print \$2}'"));
    $last = (string) shell_exec('pdftotext -f '.$pages.' -l '.$pages.' -layout '.escapeshellarg($file).' -');

    @unlink($file);

    return ['pages' => $pages, 'last' => $last];
}

beforeEach(function (): void {
    // Poppler is not everywhere; without it there is nothing to inspect.
    if (trim((string) shell_exec('command -v pdftotext')) === '') {
        test()->markTestSkipped('pdftotext (poppler-utils) nicht verfügbar.');
    }
});

it('never leaves the summary alone on the last sheet', function (int $count): void {
    // 40 and 41 are the lengths where the summary used to jump to a page of its
    // own: as a standalone block it moved whole and pulled no line item along.
    $result = renderPages($count);

    expect($result['pages'])->toBeGreaterThan(1)
        ->and($result['last'])->toContain('Gesamtbetrag')
        ->and($result['last'])->toContain('Digitaldruck Bogenoffset');
})->with([40, 41, 60]);

it('repeats the table header and the footer on the last sheet', function (): void {
    $result = renderPages(60);

    expect($result['last'])->toContain('Bezeichnung')
        ->and($result['last'])->toContain('Peppermint Digital GmbH')
        ->and($result['last'])->toContain('Seite '.$result['pages'].' von '.$result['pages']);
});
