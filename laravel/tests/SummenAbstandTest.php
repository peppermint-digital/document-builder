<?php

use Peppermint\DocumentBuilder\Data\DocumentData;
use Peppermint\DocumentBuilder\Data\LineItem;
use Peppermint\DocumentBuilder\Data\PageSetup;
use Peppermint\DocumentBuilder\Data\Totals;
use Peppermint\DocumentBuilder\DocumentBuilder;
use Peppermint\DocumentBuilder\Presets\Din5008Preset;
use Peppermint\DocumentBuilder\Renderers\DomPdfRenderer;
use Peppermint\DocumentBuilder\Services\LineItemsRenderer;
use Peppermint\DocumentBuilder\Services\PlaceholderRenderer;
use Peppermint\DocumentBuilder\Services\TotalsRenderer;

/**
 * „Positionen zu dicht zusammen" (Bug #1135, gemeldet von Volker).
 *
 * ## Was gemeldet war
 *
 * Ein Screenshot eines Angebots mit `Gesamtbetrag13.610,27 €` — Beschriftung
 * und Betrag ohne jeden Abstand. Dazu der Satz: „bei längeren Texten oder
 * höheren Summen sind die Abstände zu klein."
 *
 * ## Was gemessen wurde
 *
 * Der Beleg liess sich mit denselben Zahlen nachbauen. Daneben trat derselbe
 * Fehler in den Positionszeilen auf: `98,75 €123.437,50 €`.
 *
 * Dahinter standen zwei Ursachen, die beide nichts mit der Polsterung zu tun
 * haben, auf die der Titel zeigt:
 *
 * 1. {@see LineItemsRenderer::laeuftUeber()} bekam den ROHWERT (`1250.0`)
 *    statt des gedruckten Textes (`1.250,00`). Zahlen sind keine
 *    Zeichenketten, also fiel jede Mengen-, Preis- und Summenspalte durch die
 *    Pruefung — die Verkleinerung aus Bug #929 griff dort nie.
 * 2. Gerechnet wurde mit 170 mm Satzspiegel und ohne die Polsterung der
 *    Zelle. Ein DIN-5008-Beleg hat 165,9 mm, und eine 14-%-Spalte traegt
 *    damit 21,2 mm Text statt der angenommenen 23,8.
 *
 * Die fette Gesamtzeile ist ein eigener Fall: Sie wird nicht verkleinert —
 * sie bekommt zwei Spalten statt einer.
 */

/** Die Spalten der Standardvorlage der Verwaltung, ohne Rabattspalte. */
function summenAbstandSpalten(): array
{
    return [
        ['key' => 'position', 'label' => 'Pos.', 'width' => '6%', 'align' => 'right'],
        ['key' => 'description', 'label' => 'Bezeichnung', 'width' => '50%'],
        ['key' => 'quantity', 'label' => 'Menge', 'width' => '8%', 'align' => 'right', 'format' => 'decimal'],
        ['key' => 'unit', 'label' => 'Einheit', 'width' => '8%'],
        ['key' => 'unit_price', 'label' => 'Einzel €', 'width' => '14%', 'align' => 'right', 'format' => 'currency'],
        ['key' => 'total', 'label' => 'Gesamt €', 'width' => '14%', 'align' => 'right', 'format' => 'currency'],
    ];
}

/** Der Satzspiegel eines DIN-5008-Blattes: 210 mm minus 24,1 und 20. */
const SUMMEN_ABSTAND_SATZSPIEGEL = 165.9;

/**
 * Eine Zeile, an der NUR der Betrag die Frage entscheidet.
 *
 * Menge 1 und Einheit `Stk` sind mit Absicht kurz: Eine Menge wie `1.250,00`
 * laeuft in der 8-%-Spalte selbst ueber und wuerde jede Zeile als eng
 * ausweisen, egal welcher Betrag daneben steht.
 */
function summenAbstandZeile(float $einzelpreis): LineItem
{
    return LineItem::fromArray([
        'position' => '1',
        'description' => 'Siebdruck Textilveredelung',
        'quantity' => 1,
        'unit' => 'Stk',
        'unit_price' => $einzelpreis,
        'total' => $einzelpreis,
    ]);
}

it('misst den gedruckten Betrag, nicht den Rohwert', function (float $betrag, bool $eng) {
    $html = (new LineItemsRenderer)->render([summenAbstandZeile($betrag)], [
        'columns' => summenAbstandSpalten(),
        'content_width' => SUMMEN_ABSTAND_SATZSPIEGEL,
    ]);

    expect(str_contains($html, 'db-eng'))->toBe($eng);
})->with([
    // `123.437,50 €` sind 12 Zeichen = 23,5 mm in einer Spalte, die 21,2 mm
    // Text traegt. Vor dem 28.10.2026 kam diese Pruefung nie zustande: Der
    // Rohwert 123437.5 ist keine Zeichenkette.
    [123437.50, true],
    [1234567.80, true],
    // Passt bequem, bleibt unangetastet.
    [98.75, false],
    [7.5, false],
]);

it('nimmt die Satzbreite aus den Optionen', function () {
    $zeile = summenAbstandZeile(123437.50);
    $spalten = summenAbstandSpalten();

    // Dieselbe Zahl, dieselbe Spalte — nur ein breiteres Blatt. Auf einem
    // Satzspiegel von 200 mm traegt die 14-%-Spalte 26 mm und der Betrag
    // passt. Das ist der Unterschied, den die feste 170 verschluckt hat.
    $eng = (new LineItemsRenderer)->render([$zeile], ['columns' => $spalten, 'content_width' => 165.9]);
    $weit = (new LineItemsRenderer)->render([$zeile], ['columns' => $spalten, 'content_width' => 200.0]);

    expect($eng)->toContain('db-eng')
        ->and($weit)->not->toContain('db-eng');
});

it('gibt dem Betrag der Uebertragszeile ebenfalls zwei Spalten', function () {
    $html = (new LineItemsRenderer)->render(
        [summenAbstandZeile(100.0), summenAbstandZeile(200.0)],
        ['columns' => summenAbstandSpalten(), 'page_breaks' => [1]],
    );

    // Die Uebertragszeile ist fett gesetzt wie die Gesamtzeile und hatte
    // deshalb dasselbe Problem.
    expect($html)->toContain('db-carry')
        ->and($html)->toContain('<td class="db-align-right" colspan="2">');
});

it('gibt der Beschriftung im eigenstaendigen Summenblock eine Klasse', function () {
    $html = (new TotalsRenderer)->render(
        Totals::fromArray([
            'net' => 11437.20,
            'gross' => 13610.27,
            'taxes' => [['label' => 'zzgl. 19 % USt.', 'amount' => 2173.07]],
        ]),
    );

    // Ohne die Klasse kann das Preset der Beschriftung keinen Abstand geben —
    // die Zellen dieses Blocks haben waagerecht keine Polsterung.
    expect($html)->toContain('<td class="db-total-label">');
});

describe('auf dem Papier', function () {
    beforeEach(function (): void {
        if (trim((string) shell_exec('command -v pdftotext')) === '') {
            test()->markTestSkipped('pdftotext (poppler-utils) nicht verfügbar.');
        }
    });

    it('laesst Gesamtbetrag und Summe nicht zusammenstossen', function () {
        // Genau die Zahlen aus Volkers Screenshot.
        $daten = DocumentData::fromArray([
            'type' => 'offer',
            'subject' => 'Angebot',
            'sender' => ['name' => 'Peppermint Digital GmbH', 'lines' => ['Adam-Stegerwald-Str. 15', '30851 Langenhagen']],
            'recipient' => ['name' => 'Mustermann GmbH', 'lines' => ['Beispielweg 42', '26123 Oldenburg']],
            'meta' => ['Angebotsnummer' => 'ANG-2026-0001'],
            'line_items' => [[
                'position' => '1', 'description' => 'Textildruck DIN A4',
                'quantity' => 400, 'unit' => 'Stk', 'unit_price' => 6.95, 'total' => 12708.00,
            ]],
            'totals' => [
                'net' => 12708.00,
                'gross' => 13610.27,
                'additional' => [
                    ['label' => 'abzgl. Rabatt', 'amount' => -1270.80],
                    ['label' => 'Nettobetrag', 'amount' => 11437.20],
                ],
                'taxes' => [['label' => 'zzgl. 19 % USt.', 'amount' => 2173.07]],
            ],
        ]);

        $builder = new DocumentBuilder(
            new Din5008Preset, new DomPdfRenderer, new PlaceholderRenderer, new LineItemsRenderer, new TotalsRenderer,
        );

        $datei = tempnam(sys_get_temp_dir(), 'db-1135-').'.pdf';
        file_put_contents($datei, $builder->pdf(
            $daten, '{{ line_items }}{{ totals }}', PageSetup::din5008(), ['columns' => summenAbstandSpalten()],
        ));

        $text = (string) shell_exec('pdftotext -layout -f 1 -l 1 '.escapeshellarg($datei).' - 2>/dev/null');
        $bbox = (string) shell_exec('pdftotext -bbox -f 1 -l 1 '.escapeshellarg($datei).' - 2>/dev/null');
        @unlink($datei);

        // Der gemeldete Befund selbst: die beiden Woerter liefen zu einem
        // zusammen. Steht er wieder da, ist der Fehler zurueck.
        expect($text)->not->toContain('Gesamtbetrag13.610,27');

        $endet = preg_match('/<word [^>]*xMax="([\d.]+)"[^>]*>Gesamtbetrag</', $bbox, $m)
            ? ((float) $m[1]) * 0.3528
            : null;
        $beginnt = preg_match('/<word xMin="([\d.]+)"[^>]*>13\.610,27</', $bbox, $m2)
            ? ((float) $m2[1]) * 0.3528
            : null;

        expect($endet)->not->toBeNull()
            ->and($beginnt)->not->toBeNull()
            // Vorher war der Abstand 0. Drei Millimeter sind das, was auch die
            // Zeilen darueber tragen.
            ->and($beginnt - $endet)->toBeGreaterThan(3.0);
    });
});

/**
 * Das Preset-CSS steht im ausgelieferten HTML — und wird mitgelesen.
 *
 * Ein Lieferschein ohne Betraege wird in der Verwaltung daran geprueft, dass
 * die Woerter des Summenblocks im HTML NICHT vorkommen. Ein Kommentar im CSS,
 * der eine dieser Beschriftungen im Klartext nennt, laesst diese Pruefung
 * fehlschlagen — am 28.10.2026 genau einmal passiert, mit einem Kommentar aus
 * diesem Commit.
 *
 * Die Zusage gilt fuer jedes Produkt, das Belege ohne Preise kennt: Was im
 * Preset steht, steht beim Empfaenger im Quelltext.
 */
it('nennt keine Beschriftung des Summenblocks im ausgelieferten CSS', function () {
    $daten = DocumentData::fromArray([
        'type' => 'delivery_note',
        'subject' => 'Lieferschein',
        'sender' => ['name' => 'Peppermint Digital GmbH', 'lines' => ['Adam-Stegerwald-Str. 15']],
        'recipient' => ['name' => 'Mustermann GmbH', 'lines' => ['Beispielweg 42']],
        'line_items' => [[
            'position' => '1', 'description' => 'Textildruck DIN A4', 'quantity' => 400, 'unit' => 'Stk',
        ]],
        // Kein `totals` — dieser Beleg fuehrt keine Betraege.
    ]);

    $builder = new DocumentBuilder(
        new Din5008Preset, new DomPdfRenderer, new PlaceholderRenderer, new LineItemsRenderer, new TotalsRenderer,
    );

    $html = $builder->html($daten, '{{ line_items }}', PageSetup::din5008(), [
        'columns' => [
            ['key' => 'position', 'label' => 'Pos.', 'width' => '10%', 'align' => 'right'],
            ['key' => 'description', 'label' => 'Bezeichnung', 'width' => '70%'],
            ['key' => 'quantity', 'label' => 'Menge', 'width' => '10%', 'align' => 'right', 'format' => 'decimal'],
            ['key' => 'unit', 'label' => 'Einheit', 'width' => '10%'],
        ],
    ]);

    expect($html)->not->toContain('Gesamtbetrag')
        ->and($html)->not->toContain('Zwischensumme')
        ->and($html)->not->toContain('Nettobetrag')
        ->and($html)->not->toContain('USt.');
});
