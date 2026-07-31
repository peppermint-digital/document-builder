<?php

use Peppermint\DocumentBuilder\Data\LineItem;
use Peppermint\DocumentBuilder\Services\LineItemsRenderer;

it('writes every column width into a colgroup', function (): void {
    // Without explicit widths DomPDF's automatic table layout wraps amounts
    // onto two lines. This is a rendering requirement, not a preference.
    $html = (new LineItemsRenderer)->render([]);

    foreach (LineItemsRenderer::DEFAULT_COLUMNS as $column) {
        expect($html)->toContain('width: '.$column['width']);
    }
});

it('puts the header in a thead so it repeats after a page break', function (): void {
    $html = (new LineItemsRenderer)->render([]);

    expect($html)->toContain('<thead><tr>')
        ->and($html)->toContain('<th class="db-align-right">Pos.</th>');
});

it('formats quantities and currency for a German document', function (): void {
    $html = (new LineItemsRenderer)->render([
        LineItem::fromArray([
            'position' => '1',
            'description' => 'Digitaldruck',
            'quantity' => 1234.5,
            'unit_price' => 1137.5,
            'total' => 1404037.5,
        ]),
    ]);

    expect($html)->toContain('1.234,50')
        ->and($html)->toContain('1.137,50 €')
        ->and($html)->toContain('1.404.037,50 €');
});

it('renders the note under the description rather than in its own column', function (): void {
    $html = (new LineItemsRenderer)->render([
        LineItem::fromArray([
            'position' => '1',
            'description' => 'Digitaldruck',
            'note' => '135 g/m² matt',
        ]),
    ]);

    expect($html)->toContain('Digitaldruck<span class="db-note">135 g/m² matt</span>');
});

it('escapes customer data', function (): void {
    $html = (new LineItemsRenderer)->render([
        LineItem::fromArray([
            'position' => '1',
            'description' => 'Müller & Sohn <script>alert(1)</script>',
        ]),
    ]);

    expect($html)->toContain('Müller &amp; Sohn')
        ->and($html)->not->toContain('<script>');
});

it('reads custom columns from the extra bag', function (): void {
    $html = (new LineItemsRenderer)->render(
        [LineItem::fromArray(['position' => '1', 'description' => 'Druck', 'sku' => 'ART-4711'])],
        ['columns' => [['key' => 'sku', 'label' => 'Artikelnr.', 'width' => '20%']]],
    );

    expect($html)->toContain('ART-4711')
        ->and($html)->toContain('Artikelnr.');
});

it('shows a placeholder row when there is nothing to list', function (): void {
    $html = (new LineItemsRenderer)->render([], ['empty_text' => 'Keine Positionen']);

    expect($html)->toContain('colspan="6"')
        ->and($html)->toContain('Keine Positionen');
});
