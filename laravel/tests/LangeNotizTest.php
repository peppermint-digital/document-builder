<?php

use Peppermint\DocumentBuilder\Data\DocumentData;
use Peppermint\DocumentBuilder\Data\LineItem;
use Peppermint\DocumentBuilder\DocumentBuilder;
use Peppermint\DocumentBuilder\Presets\Din5008Preset;
use Peppermint\DocumentBuilder\Renderers\DomPdfRenderer;
use Peppermint\DocumentBuilder\Services\LineItemsRenderer;
use Peppermint\DocumentBuilder\Services\PlaceholderRenderer;
use Peppermint\DocumentBuilder\Services\TotalsRenderer;

/**
 * Eine lange Positionsnotiz darf die Seite davor nicht leer lassen (Bug #948).
 *
 * ## Was passiert ist
 *
 * Gemeldet an einer Auftragsbestaetigung: Seite 1 brach direkt nach der
 * Tabellenkopfzeile ab, der gesamte Inhalt stand auf Seite 2. Ein Kopf ueber
 * Leerraum.
 *
 * Der Beleg hatte EINE Position. Ihre Bezeichnung war 37 Zeichen kurz — der
 * umfangreiche Text stand in der NOTIZ. Die ritt bis dahin in der
 * Bezeichnungszelle derselben `<tr>` mit, und damit war die Zeile rund 160 mm
 * hoch.
 *
 * DomPDF kann eine Tabellenzeile nicht ueber eine Seitengrenze teilen. Eine
 * Zeile, die nicht mehr in den Rest der Seite passt, springt als Ganzes — und
 * zurueck bleibt der wiederholte `<thead>`.
 *
 * ## Warum PDF-Tests und nicht nur Markup
 *
 * Die Zusage lautet „Seite 1 traegt die Position", und das weiss erst das
 * Papier. Ein Markup-Test koennte nur pruefen, dass Zeilen entstehen — nicht,
 * dass sie dorthin fallen, wo sie sollen.
 */
const NOTIZ_LANG = <<<'TXT'
Wir bieten ein flexibles monatliches Mediapackage an, um gezielt im Bereich Marketing zu unterstuetzen. Der Fokus liegt auf Bewegtbild-Content. Die Konzeptideen entstehen auf Seiten des Auftraggebers.
Das Mediapackage hat eine Laufzeit bis zum 31.12.2026. Unberuehrt bleibt das Recht beider Parteien, den Vertrag aus wichtigem Grund ausserordentlich zu kuendigen.
Die monatliche Verguetung betraegt 1.990,00 EUR zuzueglich gesetzlicher Umsatzsteuer. Die Abrechnung erfolgt jeweils monatlich gegen Rechnung. Der Rechnungsbetrag ist innerhalb von 14 Tagen nach Rechnungsstellung per Ueberweisung faellig.

Das Paket umfasst folgende Leistungen, die monatlich nach Bedarf abgerufen werden koennen:

Content-Produktion (Video/Reels inkl. Postproduktion)
Grafik- und Foto-Content
Strategie und Reporting

Zusatz:
Inkludiertes monatliches Stundenvolumen 20 Stunden / Monat. Auflistungen der Zeitaufwaende erfolgt ueber ein separates Zeitkonto.
TXT;

const NOTIZ_KURZ = '135 g/m² Bilderdruck matt';

function belegMitNotiz(int $anzahl, string $notiz): DocumentData
{
    $items = [];
    $netto = 0.0;

    for ($i = 1; $i <= $anzahl; $i++) {
        $netto += 1990.0;
        $items[] = [
            'position' => (string) $i,
            'description' => 'Package XS - Monatliches Mediapackage',
            'note' => $notiz,
            'quantity' => 1,
            'unit' => 'Stk',
            'unit_price' => 1990.0,
            'total' => 1990.0,
        ];
    }

    return DocumentData::fromArray([
        'type' => 'order_confirmation',
        'subject' => 'Auftragsbestätigung AB-2026-0038',
        'sender' => ['name' => 'Peppermint Digital GmbH', 'lines' => ['Adam-Stegerwald-Str. 15', '30851 Langenhagen']],
        'recipient' => ['name' => 'Kählig Antriebstechnik GmbH', 'lines' => ['Pappelweg 4', '30179 Hannover']],
        'meta' => ['Bestätigungsnummer' => 'AB-2026-0038'],
        'line_items' => $items,
        'totals' => [
            'net' => $netto,
            'gross' => $netto * 1.19,
            'taxes' => [['label' => 'zzgl. 19 % USt.', 'amount' => $netto * 0.19]],
        ],
    ]);
}

/**
 * @return array{pages: int, erste: string}
 */
function druckeMitNotiz(int $anzahl, string $notiz): array
{
    $builder = new DocumentBuilder(
        new Din5008Preset,
        new DomPdfRenderer,
        new PlaceholderRenderer,
        new LineItemsRenderer,
        new TotalsRenderer,
    );

    $vorlage = '<p>Sehr geehrte Damen und Herren,</p><p>gerne bestätigen wir Ihren Auftrag:</p>'
        .'{{ line_items }}{{ totals }}';

    $datei = tempnam(sys_get_temp_dir(), 'db-notiz-').'.pdf';
    file_put_contents($datei, $builder->pdf(belegMitNotiz($anzahl, $notiz), $vorlage));

    $pages = (int) trim((string) shell_exec('pdfinfo '.escapeshellarg($datei)." | awk '/^Pages/{print \$2}'"));
    $erste = (string) shell_exec('pdftotext -f 1 -l 1 -layout '.escapeshellarg($datei).' -');

    @unlink($datei);

    return ['pages' => $pages, 'erste' => $erste];
}

beforeEach(function (): void {
    if (trim((string) shell_exec('command -v pdftotext')) === '') {
        test()->markTestSkipped('pdftotext (poppler-utils) nicht verfügbar.');
    }
});

it('lässt die erste Seite nicht leer, wenn eine Position eine lange Notiz trägt', function (int $anzahl): void {
    $ergebnis = druckeMitNotiz($anzahl, NOTIZ_LANG);

    // Der Befund von damals: Seite 1 trug NULL Positionen — nur den Kopf.
    expect($ergebnis['erste'])->toContain('Package XS');
})->with([1, 2, 3]);

it('setzt eine lange Notiz in eigene Zeilen, damit DomPDF umbrechen kann', function (): void {
    $html = (new LineItemsRenderer)->render([
        LineItem::fromArray([
            'position' => '1',
            'description' => 'Package XS',
            'note' => NOTIZ_LANG,
            'quantity' => 1,
            'unit' => 'Stk',
            'unit_price' => 1990.0,
            'total' => 1990.0,
        ]),
    ]);

    expect($html)->toContain('db-note-row');
});

/**
 * Und der Gegenfall, der den Satz aller übrigen Belege schützt: Eine kurze
 * Notiz bleibt in ihrer Zelle. Sie hat das Problem nicht, und eigene Zeilen
 * wären eine Änderung am Schriftbild ohne Anlass.
 */
it('lässt eine kurze Notiz in der Bezeichnungszelle', function (): void {
    $html = (new LineItemsRenderer)->render([
        LineItem::fromArray([
            'position' => '1',
            'description' => 'Package XS',
            'note' => NOTIZ_KURZ,
            'quantity' => 1,
            'unit' => 'Stk',
            'unit_price' => 1990.0,
            'total' => 1990.0,
        ]),
    ]);

    expect($html)->not->toContain('db-note-row')
        ->and($html)->toContain('db-note');
});

/**
 * Die Schwelle ist einstellbar — und `0` schaltet das Verhalten ganz ab.
 * Das ist der Notausgang, falls ein Beleg im Bestand das Aufteilen nicht
 * verträgt.
 */
it('lässt sich über die Schwelle steuern', function (): void {
    $item = LineItem::fromArray([
        'position' => '1',
        'description' => 'Package XS',
        'note' => NOTIZ_KURZ,
        'quantity' => 1,
        'unit' => 'Stk',
        'unit_price' => 1990.0,
        'total' => 1990.0,
    ]);

    expect((new LineItemsRenderer)->render([$item], ['note_rows_min_lines' => 1]))->toContain('db-note-row')
        ->and((new LineItemsRenderer)->render([$item], ['note_rows_min_lines' => 0]))->not->toContain('db-note-row');
});
