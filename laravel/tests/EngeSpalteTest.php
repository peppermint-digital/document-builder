<?php

use Peppermint\DocumentBuilder\Data\DocumentData;
use Peppermint\DocumentBuilder\Data\LineItem;
use Peppermint\DocumentBuilder\Data\PageSetup;
use Peppermint\DocumentBuilder\DocumentBuilder;
use Peppermint\DocumentBuilder\Presets\Din5008Preset;
use Peppermint\DocumentBuilder\Renderers\DomPdfRenderer;
use Peppermint\DocumentBuilder\Services\LineItemsRenderer;
use Peppermint\DocumentBuilder\Services\PlaceholderRenderer;
use Peppermint\DocumentBuilder\Services\TotalsRenderer;

/**
 * Ein zu langes Wort darf nicht in die Nachbarspalte laufen (Bug #929).
 *
 * ## Was gemeldet war
 *
 * Auf einer Rechnung stand `Pauschal7.450,00 €` — Einheit und Einzelpreis
 * ohne jeden Abstand aneinander. Gemeldet als „Abstände müssen vergrößert
 * bzw. Schriftgröße verkleinert werden".
 *
 * ## Was gemessen wurde
 *
 * Bei 170 mm Satzspiegel und den Spalten der Standardvorlage (Einheit 8 %)
 * belegt `Pauschal` 15,7 mm in einer 13,6 mm breiten Spalte. Es laeuft also
 * 2,1 mm heraus. Ob daraus eine Beruehrung wird, entscheidet allein die
 * Laenge des Betrags daneben — bei sechsstelligen Betraegen ueberlappen sie
 * sich um 0,7 mm.
 *
 * Deshalb pruefen die Tests hier BEIDES: dass die Zelle als eng erkannt wird,
 * und dass auf dem Papier ein Abstand bleibt.
 */

/** Die Spalten der Standardvorlage der Verwaltung, ohne Rabattspalte. */
function engeSpalten(): array
{
    return [
        ['key' => 'position', 'label' => 'Pos.', 'width' => '6%', 'align' => 'right'],
        ['key' => 'description', 'label' => 'Bezeichnung', 'width' => '40%'],
        ['key' => 'quantity', 'label' => 'Menge', 'width' => '8%', 'align' => 'right', 'format' => 'decimal'],
        ['key' => 'unit', 'label' => 'Einheit', 'width' => '8%'],
        ['key' => 'unit_price', 'label' => 'Einzel €', 'width' => '14%', 'align' => 'right', 'format' => 'currency'],
        ['key' => 'total', 'label' => 'Gesamt €', 'width' => '14%', 'align' => 'right', 'format' => 'currency'],
    ];
}

function zeileMitEinheit(string $einheit, float $betrag = 7450.0): LineItem
{
    return LineItem::fromArray([
        'position' => '1',
        'description' => 'Professionelle Eventbegleitung',
        'quantity' => 1,
        'unit' => $einheit,
        'unit_price' => $betrag,
        'total' => $betrag,
    ]);
}

it('setzt ein zu langes Einzelwort kleiner', function (string $einheit, bool $eng) {
    $html = (new LineItemsRenderer)->render([zeileMitEinheit($einheit)], ['columns' => engeSpalten()]);

    expect(str_contains($html, 'db-eng'))->toBe($eng);
})->with([
    // `Pauschal` belegt 15,7 mm in 13,6 mm — der gemeldete Fall.
    ['Pauschal', true],
    ['Stunden', true],
    // Passt bequem, bleibt unangetastet.
    ['Stk', false],
    ['Tage', false],
]);

/**
 * Und der Gegenfall, der den Satz der Bezeichnungsspalte schuetzt: Eine lange
 * BESCHREIBUNG bricht um und darf deshalb nie schrumpfen. Wuerde die Regel
 * auch auf sie zugreifen, waere jede ausfuehrliche Position kleiner gesetzt
 * als der Rest des Belegs.
 */
it('laesst mehrwortigen Text in Ruhe, weil er umbricht', function () {
    $html = (new LineItemsRenderer)->render([
        LineItem::fromArray([
            'position' => '1',
            'description' => 'Professionelle Eventbegleitung mit Foto- und Videodokumentation vor Ort',
            'quantity' => 1,
            'unit' => 'Stk',
            'unit_price' => 10.0,
            'total' => 10.0,
        ]),
    ], ['columns' => engeSpalten()]);

    expect($html)->not->toContain('db-eng');
});

beforeEach(function (): void {
    if (trim((string) shell_exec('command -v pdftotext')) === '') {
        test()->markTestSkipped('pdftotext (poppler-utils) nicht verfügbar.');
    }
});

/**
 * Die Zusage, auf die es ankommt: auf dem Papier bleibt ein Abstand.
 *
 * Geprueft mit einem SECHSSTELLIGEN Betrag — dem Fall, in dem der Betrag am
 * weitesten nach links reicht und der Zusammenstoss unvermeidlich waere.
 */
it('laesst Einheit und Betrag auf dem Papier nicht zusammenstossen', function () {
    $daten = DocumentData::fromArray([
        'type' => 'invoice',
        'subject' => 'Rechnung',
        'sender' => ['name' => 'Peppermint Digital GmbH', 'lines' => ['Adam-Stegerwald-Str. 15', '30851 Langenhagen']],
        'recipient' => ['name' => 'Mustermann GmbH', 'lines' => ['Beispielweg 42', '26123 Oldenburg']],
        'meta' => ['Rechnungsnummer' => 'RE-2026-0001'],
        'line_items' => [[
            'position' => '1', 'description' => 'Professionelle Eventbegleitung',
            'quantity' => 1, 'unit' => 'Pauschal', 'unit_price' => 174500.0, 'total' => 174500.0,
        ]],
        'totals' => ['net' => 174500.0, 'gross' => 207655.0, 'taxes' => [['label' => 'zzgl. 19 % USt.', 'amount' => 33155.0]]],
    ]);

    $builder = new DocumentBuilder(
        new Din5008Preset, new DomPdfRenderer, new PlaceholderRenderer, new LineItemsRenderer, new TotalsRenderer,
    );

    $datei = tempnam(sys_get_temp_dir(), 'db-eng-').'.pdf';
    file_put_contents($datei, $builder->pdf(
        $daten, '{{ line_items }}{{ totals }}', PageSetup::din5008(), ['columns' => engeSpalten()],
    ));

    $bbox = (string) shell_exec('pdftotext -bbox -f 1 -l 1 '.escapeshellarg($datei).' - 2>/dev/null');
    @unlink($datei);

    $rechteKante = static function (string $wort) use ($bbox): ?float {
        return preg_match('/<word [^>]*xMax="([\d.]+)"[^>]*>'.preg_quote($wort, '/').'</', $bbox, $m)
            ? ((float) $m[1]) * 0.3528
            : null;
    };
    $linkeKante = static function (string $wort) use ($bbox): ?float {
        return preg_match('/<word xMin="([\d.]+)"[^>]*>'.preg_quote($wort, '/').'</', $bbox, $m)
            ? ((float) $m[1]) * 0.3528
            : null;
    };

    $einheitEndet = $rechteKante('Pauschal');
    $betragBeginnt = $linkeKante('174.500,00');

    expect($einheitEndet)->not->toBeNull()
        ->and($betragBeginnt)->not->toBeNull()
        // Ohne den kleineren Grad endet `Pauschal` bei 140,3 mm und der Betrag
        // beginnt bei 139,6 — sie ueberlappen sich um 0,7 mm.
        ->and($betragBeginnt - $einheitEndet)->toBeGreaterThan(1.0);
});
