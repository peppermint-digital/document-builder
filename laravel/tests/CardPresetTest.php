<?php

use Peppermint\DocumentBuilder\Data\CardData;
use Peppermint\DocumentBuilder\Data\PageSetup;
use Peppermint\DocumentBuilder\Data\SheetSetup;
use Peppermint\DocumentBuilder\Presets\CardPreset;

it('emits flat CSS only', function (): void {
    $css = (new CardPreset(SheetSetup::grid(61, 54, columns: 2, rows: 4)))->css(PageSetup::din5008());

    // DomPDF's default media type is screen, and it supports neither of these.
    expect($css)->not->toContain('@media')
        ->and($css)->not->toContain('display: flex')
        ->and($css)->not->toContain('display: grid');
});

it('derives a type size that matches both hand-set badges', function (): void {
    // This is the mechanism that lets one template serve both sizes. If it
    // drifts, the migrated badges come out visibly wrong — and "visibly wrong"
    // means a box of unusable printed cards, not a failing page.
    $umhaenger = new CardPreset(SheetSetup::single(76, 124));
    $aufkleber = new CardPreset(SheetSetup::grid(61, 54, columns: 2, rows: 4));

    $titelGross = $umhaenger->basisSchriftgroesse() * 2.2;
    $titelKlein = $aufkleber->basisSchriftgroesse() * 2.2;

    // Hand-set in the blades: 28pt on the hanging badge, 18pt on the label.
    expect($titelGross)->toBeGreaterThan(27.0)->toBeLessThan(29.0)
        ->and($titelKlein)->toBeGreaterThan(17.5)->toBeLessThan(19.5);
});

it('gives a taller card larger type', function (): void {
    $klein = (new CardPreset(SheetSetup::grid(61, 54, columns: 2, rows: 4)))->basisSchriftgroesse();
    $gross = (new CardPreset(SheetSetup::single(76, 124)))->basisSchriftgroesse();

    expect($gross)->toBeGreaterThan($klein);
});

it('places each card at its own offset in the grid', function (): void {
    $sheet = new SheetSetup(
        page: new PageSetup(marginTop: 15, marginRight: 15, marginBottom: 15, marginLeft: 15),
        cardWidth: 61,
        cardHeight: 54,
        columns: 2,
        rows: 2,
        gutterX: 8,
        gutterY: 6,
    );

    $html = (new CardPreset($sheet))->renderSheet(['A', 'B', 'C', 'D'], $sheet->page);

    expect($html)->toContain('left: 0mm; top: 0mm;')      // erste Spalte, erste Zeile
        ->and($html)->toContain('left: 69mm; top: 0mm;')   // 61 + 8 Steg
        ->and($html)->toContain('left: 0mm; top: 60mm;')   // 54 + 6 Steg
        ->and($html)->toContain('left: 69mm; top: 60mm;');
});

it('starts a new sheet when the grid is full', function (): void {
    $sheet = SheetSetup::grid(61, 54, columns: 2, rows: 2);
    $html = (new CardPreset($sheet))->renderSheet(['A', 'B', 'C', 'D', 'E'], $sheet->page);

    expect(substr_count($html, 'class="db-sheet"'))->toBe(2)
        ->and($html)->toContain('page-break-before: always');
});

it('draws no crop marks unless asked', function (): void {
    $sheet = SheetSetup::grid(61, 54, columns: 2, rows: 2);
    $html = (new CardPreset($sheet))->renderSheet(['A'], $sheet->page);

    expect($html)->not->toContain('class="db-mark"');
});

it('draws crop marks outside the card, never on it', function (): void {
    $sheet = SheetSetup::grid(61, 54, columns: 2, rows: 2, cropMarks: true);
    $html = (new CardPreset($sheet))->renderSheet(['A'], $sheet->page);

    // A mark drawn inside would be cut off along with the edge it marks.
    // The first card sits at top 0, so its lower mark belongs below 54mm.
    expect($html)->toContain('class="db-mark"')
        ->and($html)->toContain('top: 55.5mm');
});

it('refuses a business document', function (): void {
    $sheet = SheetSetup::single(76, 124);

    expect(fn () => (new CardPreset($sheet))->render(offer(), '<p>x</p>', $sheet->page))
        ->toThrow(InvalidArgumentException::class, CardData::class);
});

it('renders a single card as a sheet of one', function (): void {
    $sheet = SheetSetup::single(76, 124);
    $karte = new CardData(type: 'badge', title: 'Anna Ahlers');

    $html = (new CardPreset($sheet))->render($karte, '<p>Anna Ahlers</p>', $sheet->page);

    expect(substr_count($html, 'class="db-card"'))->toBe(1)
        ->and($html)->toContain('Anna Ahlers');
});

it('appends a design\'s own CSS once, after the skeleton', function (): void {
    $sheet = SheetSetup::grid(61, 54, columns: 2, rows: 2);
    $css = (new CardPreset($sheet))->css($sheet->page, ['card_css' => '.badge { color: rebeccapurple; }']);

    // Nach dem Skelett, damit ein Entwurf gezielt ueberschreiben kann.
    expect($css)->toContain('rebeccapurple')
        ->and(strpos($css, 'rebeccapurple'))->toBeGreaterThan(strpos($css, '.db-card'));
});

it('carries a design\'s CSS into the sheet exactly once', function (): void {
    $sheet = SheetSetup::grid(61, 54, columns: 2, rows: 2);
    $html = (new CardPreset($sheet))->renderSheet(['A', 'B', 'C'], $sheet->page, ['card_css' => '.badge { color: red; }']);

    // Ein Stilblock je Karte waere bei zweihundert Schildern zweihundertmal
    // im PDF.
    expect(substr_count($html, '.badge { color: red; }'))->toBe(1);
});

it('keeps a card exactly its size even when a design adds a border', function (): void {
    $sheet = SheetSetup::grid(61, 54, columns: 2, rows: 2);
    $css = (new CardPreset($sheet))->css($sheet->page);

    // Ohne das laeuft ein Rahmen ueber die Kante und `overflow: hidden`
    // schneidet ihn ab — sichtbar als fehlende Linie an einer Seite.
    expect($css)->toContain('box-sizing: border-box');
});
