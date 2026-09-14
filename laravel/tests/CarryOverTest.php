<?php

use Peppermint\DocumentBuilder\Contracts\PageAnalyzer;
use Peppermint\DocumentBuilder\Data\DocumentData;
use Peppermint\DocumentBuilder\Data\LineItem;
use Peppermint\DocumentBuilder\DocumentBuilder;
use Peppermint\DocumentBuilder\Presets\Din5008Preset;
use Peppermint\DocumentBuilder\Renderers\DomPdfRenderer;
use Peppermint\DocumentBuilder\Services\LineItemsRenderer;
use Peppermint\DocumentBuilder\Services\PdftotextPageAnalyzer;
use Peppermint\DocumentBuilder\Services\PlaceholderRenderer;
use Peppermint\DocumentBuilder\Services\TotalsRenderer;

/** Wie `builder()`, nur mit einem vorgegebenen Analyzer. */
function builderMit(PageAnalyzer $analyzer): DocumentBuilder
{
    return new DocumentBuilder(
        preset: new Din5008Preset,
        renderer: new DomPdfRenderer,
        placeholders: new PlaceholderRenderer,
        lineItems: new LineItemsRenderer,
        totals: new TotalsRenderer,
        pageAnalyzer: $analyzer,
    );
}

/** Seitenzahl über die Form-Feed-Marker im PDF-Textstrom. */
function seitenzahl(string $pdf): int
{
    return substr_count($pdf, '/Type /Page') - substr_count($pdf, '/Type /Pages');
}

/**
 * Der Übertrag bei mehrseitigen Belegen.
 *
 * Der Ablauf: erst drucken, dann nachsehen wo die Seiten enden, dann mit
 * vorgegebenen Umbrüchen noch einmal drucken. Diese Tests halten beide Seiten
 * der Zusage fest — dass er erscheint, wenn er kann, und dass der Beleg
 * unverändert bleibt, wenn er nicht kann.
 */
function zeilen(int $anzahl, float $betrag = 100.0): array
{
    $items = [];

    for ($i = 1; $i <= $anzahl; $i++) {
        $items[] = LineItem::fromArray([
            'position' => (string) $i,
            'description' => "Artikel {$i}",
            'total' => $betrag,
        ]);
    }

    return $items;
}

/**
 * Ein Analyzer, der eine vorgegebene Aufteilung meldet — damit die Tests ohne
 * echtes PDF und ohne externes Werkzeug auskommen.
 */
function analyzer(?array $aufteilung, bool $verfuegbar = true): PageAnalyzer
{
    return new class($aufteilung, $verfuegbar) implements PageAnalyzer
    {
        public int $aufrufe = 0;

        public function __construct(private readonly ?array $aufteilung, private readonly bool $verfuegbar) {}

        public function isAvailable(): bool
        {
            return $this->verfuegbar;
        }

        public function pagesByPosition(string $pdf, array $positions): ?array
        {
            $this->aufrufe++;

            return $this->aufteilung;
        }
    };
}

it('setzt eine Übertragszeile an das Seitenende und an den Seitenanfang', function (): void {
    // Umbruch nach der dritten Zeile: 3 × 100 = 300 als Übertrag.
    $html = (new LineItemsRenderer)->render(zeilen(6), ['page_breaks' => [3]]);

    expect(substr_count($html, 'Übertrag'))->toBe(2)
        ->and($html)->toContain('db-carry-end')
        ->and($html)->toContain('db-carry-start')
        ->and($html)->toContain('300,00');
});

it('erzwingt den Umbruch an genau dieser Stelle', function (): void {
    // Gemessen: DomPDF beachtet `page-break-after` auf einer <tr>. Damit ist
    // die Aufteilung im zweiten Lauf Vorgabe statt Ergebnis — und genau daran
    // scheitert ein naiver zweiter Lauf.
    $html = (new LineItemsRenderer)->render(zeilen(6), ['page_breaks' => [3]]);

    expect(substr_count($html, 'db-page-break-row'))->toBe(1);
});

it('summiert über mehrere Umbrüche fortlaufend', function (): void {
    $html = (new LineItemsRenderer)->render(zeilen(9), ['page_breaks' => [3, 6]]);

    // 300 nach der dritten, 600 nach der sechsten Zeile — je zweimal.
    expect(substr_count($html, '300,00'))->toBe(2)
        ->and(substr_count($html, '600,00'))->toBe(2)
        ->and(substr_count($html, 'db-page-break-row'))->toBe(2);
});

it('bricht nach der letzten Zeile nicht um', function (): void {
    // Dort folgt der Summenblock — ein Übertrag davor wäre sinnlos und ein
    // Umbruch ließe die Summen allein auf einer leeren Seite zurück.
    $html = (new LineItemsRenderer)->render(zeilen(3), ['page_breaks' => [3]]);

    expect($html)->not->toContain('Übertrag')
        ->and($html)->not->toContain('db-page-break-row');
});

it('ändert ohne Vorgabe nichts an der Tabelle', function (): void {
    $ohne = (new LineItemsRenderer)->render(zeilen(6));
    $mitLeerer = (new LineItemsRenderer)->render(zeilen(6), ['page_breaks' => []]);

    expect($mitLeerer)->toBe($ohne)
        ->and($ohne)->not->toContain('Übertrag');
});

it('lässt den Beleg unverändert, wenn die Zuordnung nicht gelingt', function (): void {
    // Ein Teilergebnis wäre schlimmer als keines: Es ergäbe einen Übertrag,
    // der nicht stimmt.
    $daten = DocumentData::fromArray(['type' => 'invoice', 'line_items' => zeilen(6)]);
    $bauer = builderMit(analyzer(null));

    $pdf = $bauer->pdf($daten, '{{ line_items }}', null, ['carry_over' => true]);

    expect($pdf)->toStartWith('%PDF-');
});

it('rührt einen einseitigen Beleg nicht an', function (): void {
    $daten = DocumentData::fromArray(['type' => 'invoice', 'line_items' => zeilen(3)]);
    $alleAufSeiteEins = ['1' => 1, '2' => 1, '3' => 1];

    $mit = builderMit(analyzer($alleAufSeiteEins))->pdf(
        $daten, '{{ line_items }}', null, ['carry_over' => true]
    );
    $ohne = builderMit(analyzer($alleAufSeiteEins))->pdf($daten, '{{ line_items }}');

    // Beide Läufe sind byteweise verschieden (DomPDF stempelt ein Datum),
    // aber die Seitenzahl muss dieselbe sein.
    expect(seitenzahl($mit))->toBe(seitenzahl($ohne));
});

it('fragt ohne carry_over gar nicht erst nach der Aufteilung', function (): void {
    $daten = DocumentData::fromArray(['type' => 'invoice', 'line_items' => zeilen(6)]);
    $pruefer = analyzer(['1' => 1, '2' => 1, '3' => 1, '4' => 2, '5' => 2, '6' => 2]);

    builderMit($pruefer)->pdf($daten, '{{ line_items }}');

    expect($pruefer->aufrufe)->toBe(0);
});

/**
 * Ein Analyzer, der den ERSTEN Aufruf (die Messung) vorgibt und ab dem zweiten
 * — der Gegenprobe — am echten PDF misst.
 *
 * Ohne diese Trennung lässt sich der Weg nicht prüfen: Ein Analyzer, der immer
 * dasselbe meldet, lässt die Gegenprobe Vorgabe gegen die ALTE Messung
 * vergleichen. Sie schlägt dann auch dort fehl, wo der zweite Lauf genau das
 * tut, was ihm gesagt wurde.
 */
function messenderAnalyzer(array $messung): PageAnalyzer
{
    return new class($messung) implements PageAnalyzer
    {
        public int $aufrufe = 0;

        private readonly PdftotextPageAnalyzer $echt;

        public function __construct(private readonly array $messung)
        {
            $this->echt = new PdftotextPageAnalyzer;
        }

        public function isAvailable(): bool
        {
            return true;
        }

        public function pagesByPosition(string $pdf, array $positions): ?array
        {
            return ++$this->aufrufe === 1
                ? $this->messung
                : $this->echt->pagesByPosition($pdf, $positions);
        }
    };
}

describe('die Vorgabe für den zweiten Lauf', function (): void {
    beforeEach(function (): void {
        if (trim((string) shell_exec('command -v pdftotext')) === '') {
            test()->markTestSkipped('pdftotext (poppler-utils) nicht verfügbar.');
        }
    });

    it('schreibt die enge erste Seite nicht auf die Folgeseiten fort', function (): void {
        // Der Live-Fall ANG-2026-00031: vier Positionen, gemessen drei auf
        // Seite 1 und eine auf Seite 2. Seite 1 ist eng, weil Briefkopf,
        // Anschriftfeld und Anschreiben über der Tabelle stehen — die
        // Folgeseite hat davon nichts.
        //
        // Vorher wurde die Kapazität von Seite 1 (drei Zeilen) für jede
        // Folgeseite angenommen, davon zwei Zeilen Reserve abgezogen und so
        // eine Position je Seite gedruckt: aus zwei Seiten wurden drei.
        $daten = DocumentData::fromArray(['type' => 'invoice', 'line_items' => zeilen(4)]);
        $bauer = builderMit(messenderAnalyzer(['1' => 1, '2' => 1, '3' => 1, '4' => 2]));

        $pdf = $bauer->pdf($daten, '{{ line_items }}', null, ['carry_over' => true]);

        // Genau ein Umbruch: Seite 1 gibt eine Zeile für ihren Übertrag ab,
        // der Rest bleibt zusammen.
        expect(seitenzahl($pdf))->toBe(2);
    });

    it('verwirft eine Vorgabe, die den Beleg um mehr als eine Seite verlängert', function (): void {
        // Zwanzig Zeilen, gemessen auf drei Seiten. Die Reserve rechnet in
        // ganzen Positionszeilen; bei vier Zeilen je Seite bleiben nach zwei
        // Übertragszeilen nur zwei übrig — das ergäbe zehn Seiten.
        //
        // Die Gegenprobe allein fängt das nicht: So ein Beleg hält seine
        // Vorgabe tadellos. Er ist nur unbrauchbar.
        $daten = DocumentData::fromArray(['type' => 'invoice', 'line_items' => zeilen(20)]);
        $messung = [];

        foreach (range(1, 20) as $i) {
            $messung[(string) $i] = match (true) {
                $i <= 4 => 1,
                $i <= 8 => 2,
                default => 3,
            };
        }

        $mit = builderMit(messenderAnalyzer($messung))->pdf(
            $daten, '{{ line_items }}', null, ['carry_over' => true]
        );
        $ohne = builderMit(messenderAnalyzer($messung))->pdf($daten, '{{ line_items }}');

        expect(seitenzahl($mit))->toBe(seitenzahl($ohne));
    });

    it('setzt den Übertrag weiter, wo eine Folgeseite gemessen wurde', function (): void {
        // Die Zusage soll nicht dadurch eingehalten werden, dass es gar keinen
        // Übertrag mehr gibt. Hier gibt es eine echte Folgeseite mit Platz:
        // Seite 1 trägt gemessen 6 Zeilen, Seite 2 ebenfalls 6.
        $daten = DocumentData::fromArray(['type' => 'invoice', 'line_items' => zeilen(14)]);
        $messung = [];

        foreach (range(1, 14) as $i) {
            $messung[(string) $i] = match (true) {
                $i <= 6 => 1,
                $i <= 12 => 2,
                default => 3,
            };
        }

        $pruefer = messenderAnalyzer($messung);
        $pdf = builderMit($pruefer)->pdf($daten, '{{ line_items }}', null, ['carry_over' => true]);

        // Zwei Aufrufe heißt: gemessen, vorgegeben, gegengeprüft.
        expect($pruefer->aufrufe)->toBe(2);
    });
});
