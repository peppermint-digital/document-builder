<?php

use Peppermint\DocumentBuilder\Data\PageSetup;
use Peppermint\DocumentBuilder\Presets\Din5008Preset;

it('places the address field and subject line at the DIN offsets', function (): void {
    $css = (new Din5008Preset)->css(PageSetup::din5008());

    // 45mm and 98.4mm from the paper edge, converted to the content box.
    expect($css)->toContain('top: 28.1mm')
        ->and($css)->toContain('margin-top: 81.5mm')
        ->and($css)->toContain('width: 85mm');
});

it('emits flat CSS only', function (): void {
    $css = (new Din5008Preset)->css(PageSetup::din5008());

    // DomPDF's default media type is screen, and it supports neither of these.
    expect($css)->not->toContain('@media')
        ->and($css)->not->toContain('display: flex')
        ->and($css)->not->toContain('display: grid');
});

it('lets the column definition decide the header alignment', function (): void {
    $css = (new Din5008Preset)->css(PageSetup::din5008());

    // Der Kopf trug ein pauschales `text-align: left`, das die Ausrichtung aus
    // der Spaltendefinition wegen hoeherer Spezifitaet schlug: Ueber einem
    // rechtsbuendigen Betrag stand eine linksbuendige Ueberschrift. Die Vorgabe
    // gehoert deshalb an die Tabelle, nicht an den Kopf.
    expect($css)->not->toContain("thead th {\n            border-bottom: 0.4mm solid #1a1a1a;\n            padding: 1.5mm 1mm;\n            text-align: left;")
        ->and($css)->toContain('table.db-line-items .db-align-right { text-align: right; }');
});

it('keeps an amount from breaking between number and currency symbol', function (): void {
    $css = (new Din5008Preset)->css(PageSetup::din5008());

    // Zweite Haelfte des Riegels — die erste ist das geschuetzte Leerzeichen im
    // LineItemsRenderer. Das eine beseitigt die Trennstelle, das andere haelt
    // die Zelle zusammen, wenn die Spalte zu schmal geraet.
    expect($css)->toContain('white-space: nowrap');
});

it('keeps the totals block from being torn across a page break', function (): void {
    $css = (new Din5008Preset)->css(PageSetup::din5008());

    expect($css)->toContain('page-break-inside: avoid');
});

it('renders the recipient into the address field and the meta into the info block', function (): void {
    $html = (new Din5008Preset)->render(offer(), '<p>body</p>', PageSetup::din5008());

    expect($html)->toContain('Mustermann &amp; Sohn GmbH')
        ->and($html)->toContain('class="db-address-supplement">Einschreiben')
        ->and($html)->toContain('Angebotsnummer')
        ->and($html)->toContain('AN-2026-0815');
});

it('draws page numbers through a page script', function (): void {
    $html = (new Din5008Preset)->render(offer(), '', PageSetup::din5008());

    expect($html)->toContain('page_script')
        ->and($html)->toContain('$PAGE_COUNT');
});

it('omits page numbers and fold marks when they are switched off', function (): void {
    $html = (new Din5008Preset)->render(offer(), '', PageSetup::din5008(), [
        'page_numbers' => false,
        'fold_marks' => false,
    ]);

    // The stylesheet always carries the rules; what must disappear is the
    // element itself.
    expect($html)->not->toContain('page_script')
        ->and($html)->not->toContain('<div class="db-mark db-mark-fold-1">');
});

it('falls back to the sender block so a document is never printed without its details', function (): void {
    $html = (new Din5008Preset)->render(offer(), '', PageSetup::din5008());

    expect($html)->toContain('class="db-footer"')
        ->and($html)->toContain('Peppermint Digital GmbH');
});

it('appends application CSS after its own so the host can win on equal specificity', function (): void {
    $html = (new Din5008Preset)->render(
        offer(),
        '<p class="beleg-anrede">Guten Tag,</p>',
        PageSetup::din5008(),
        ['extra_css' => '.beleg-anrede { margin-bottom: 6mm; }'],
    );

    $skelett = strpos($html, 'table.db-line-items');
    $eigenes = strpos($html, '.beleg-anrede { margin-bottom: 6mm; }');

    // Die Reihenfolge IST die Zusicherung: Bei gleicher Spezifitaet gewinnt in
    // CSS die spaetere Regel. Stuende das Anwendungs-CSS davor, waere jede
    // Ueberschreibung wirkungslos — und zwar lautlos.
    expect($eigenes)->not->toBeFalse()
        ->and($eigenes)->toBeGreaterThan($skelett)
        ->and($html)->toContain('</style>');
});

it('emits no stray newline when the application supplies no CSS', function (): void {
    $html = (new Din5008Preset)->render(offer(), '<p>x</p>', PageSetup::din5008(), []);

    expect($html)->not->toContain("\n</style>");
});

/**
 * Der Waechter. Diese Regel ist der ganze Grund, warum die Masse als geschlossene
 * Formen und nicht als lose Konstanten dastehen.
 *
 * Vorher trug das Skelett das Anschriftfeld der Form B (45mm) und die Falzmarken
 * der Form A (87/192mm). Der erste Falz lag damit 3mm oberhalb der Unterkante des
 * Anschriftfelds — der Knick lief durch die Zeile mit Postleitzahl und Ort, also
 * durch genau die Zeile, die im Fensterumschlag lesbar sein muss. Kein Fehler,
 * kein Log: Das sieht man erst auf gefaltetem Papier.
 */
it('never places a fold mark inside the address field', function (string $form): void {
    $masse = Din5008Preset::form(['form' => $form]);

    $oben = $masse['address_top'];
    $unten = $oben + Din5008Preset::addressHeight();

    foreach (['fold_one', 'fold_two'] as $marke) {
        expect($masse[$marke])->not->toBeBetween(
            $oben,
            $unten,
            "Falzmarke {$marke} der Form {$form} liegt im Anschriftfeld ({$oben}mm–{$unten}mm).",
        );
    }
})->with(['a', 'b']);

it('keeps every fold panel short enough for a DIN lang envelope', function (string $form): void {
    $masse = Din5008Preset::form(['form' => $form]);

    // A4 ist 297mm hoch, der Innenraum eines DIN-lang-Umschlags rund 110mm.
    $panels = [$masse['fold_one'], $masse['fold_two'] - $masse['fold_one'], 297.0 - $masse['fold_two']];

    foreach ($panels as $hoehe) {
        expect($hoehe)->toBeLessThanOrEqual(110.0)->and($hoehe)->toBeGreaterThan(0.0);
    }
})->with(['a', 'b']);

it('shifts the subject line by the same amount as the address field', function (): void {
    $a = Din5008Preset::form(['form' => 'a']);
    $b = Din5008Preset::form(['form' => 'b']);

    // Beide Formen unterscheiden sich nur darin, wie viel Platz oben fuer den
    // Briefkopf bleibt. Verschoebe sich der Betreff um einen anderen Betrag als
    // das Anschriftfeld, liefe er in den Infoblock daneben.
    expect($b['subject_top'] - $a['subject_top'])->toBe($b['address_top'] - $a['address_top']);
});

it('falls back to the default form instead of throwing on an unknown name', function (): void {
    // Ein Tippfehler in einer Vorlagen-Einstellung darf keine Rechnung aufhalten.
    expect(Din5008Preset::form(['form' => 'gibt-es-nicht']))->toBe(Din5008Preset::form([]));
});

it('draws the fold marks of the requested form', function (): void {
    $a = (new Din5008Preset)->css(PageSetup::din5008(), ['form' => 'a']);
    $b = (new Din5008Preset)->css(PageSetup::din5008(), ['form' => 'b']);

    // 87mm und 105mm von der Papierkante, umgerechnet auf die Inhaltsbox (16.9mm oben).
    expect($a)->toContain('top: 70.1mm')
        ->and($b)->toContain('top: 88.1mm');
});
