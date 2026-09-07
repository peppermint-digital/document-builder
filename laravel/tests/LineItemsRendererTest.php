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
        // Klasse und Beschriftung, nicht die exakte Zeichenkette: Die Kopfzelle
        // traegt seit `table-layout: fixed` zusaetzlich ihre Breite, und die
        // Probe soll die Ausrichtung pruefen, nicht die Attributreihenfolge.
        ->and($html)->toMatch('/<th class="db-align-right"[^>]*>Pos\.<\/th>/');
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

    // Geschuetztes Leerzeichen zwischen Zahl und Zeichen — ein normales
    // erlaubte den Umbruch, und ein „€" allein auf der naechsten Zeile liest
    // sich wie ein anderer Betrag.
    expect($html)->toContain('1.234,50')
        ->and($html)->toContain("1.137,50\u{00A0}€")
        ->and($html)->toContain("1.404.037,50\u{00A0}€")
        ->and($html)->not->toContain('1.137,50 €');
});

it('keeps an amount on one line even in a narrow column', function (): void {
    // Das geschuetzte Leerzeichen beseitigt die Trennstelle; `nowrap` haelt die
    // Zelle zusammen, falls die Spalte trotzdem zu schmal geraet. Beides wird
    // gebraucht — deshalb wird auch beides geprueft, das zweite im Preset.
    $html = (new LineItemsRenderer)->render([
        LineItem::fromArray([
            'position' => '1',
            'description' => 'Digitaldruck',
            'quantity' => 1,
            'unit_price' => 1137.5,
            'total' => 1137.5,
        ]),
    ]);

    expect($html)->not->toMatch('/\d,\d{2} €/');
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

it('keeps the line breaks inside a note', function (): void {
    // Die Notiz traegt in der Praxis eine Aufzaehlung — Artikelnummer, Farbe,
    // Groessenaufteilung je Zeile. Ohne <br> lief sie zu einem Fliesstext
    // zusammen, in dem „100 x Groesse S" und „100 x Groesse M" nebeneinander
    // standen und nicht mehr auseinanderzuhalten waren.
    $html = (new LineItemsRenderer)->render([
        LineItem::fromArray([
            'position' => '1',
            'description' => 'T-Shirt',
            'note' => "Art.-Nr.: F140\n100 x Größe S\n100 x Größe M",
        ]),
    ]);

    // nl2br ERSETZT den Umbruch nicht, es stellt das <br> davor — im Markup
    // steht also "<br>\n". Fuer die Darstellung ist der rohe Umbruch danach
    // nur Leerraum; geprueft wird deshalb das Tag, nicht die genaue Folge.
    expect($html)->toContain('Art.-Nr.: F140<br>')
        ->and($html)->toContain('100 x Größe S<br>')
        ->and(substr_count($html, '<br>'))->toBe(2)
        // XHTML-Schreibweise waere ebenfalls gueltig, aber DomPDF bekommt hier
        // bewusst dasselbe Markup wie der Rest des Dokuments.
        ->and($html)->not->toContain('<br />');
});

it('still escapes a note that contains markup', function (): void {
    // nl2br darf die Maskierung nicht aushebeln: Erst escapen, dann Umbrueche.
    $html = (new LineItemsRenderer)->render([
        LineItem::fromArray([
            'position' => '1',
            'description' => 'T-Shirt',
            'note' => "<script>alert(1)</script>\nzweite Zeile",
        ]),
    ]);

    expect($html)->not->toContain('<script>')
        ->and($html)->toContain('&lt;script&gt;')
        ->and($html)->toContain('<br>');
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
