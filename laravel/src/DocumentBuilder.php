<?php

namespace Peppermint\DocumentBuilder;

use Peppermint\DocumentBuilder\Contracts\DocumentPreset;
use Peppermint\DocumentBuilder\Contracts\DocumentRenderer;
use Peppermint\DocumentBuilder\Contracts\PageAnalyzer;
use Peppermint\DocumentBuilder\Data\DocumentData;
use Peppermint\DocumentBuilder\Data\LineItem;
use Peppermint\DocumentBuilder\Data\PageSetup;
use Peppermint\DocumentBuilder\Services\LineItemsRenderer;
use Peppermint\DocumentBuilder\Services\PlaceholderRenderer;
use Peppermint\DocumentBuilder\Services\TotalsRenderer;

/**
 * The one entry point a host application needs.
 *
 * Takes the body a template produced for the free zones, fills in the two
 * blocks that cannot be placeholders, substitutes the rest, wraps everything in
 * the preset skeleton and hands it to the renderer driver.
 */
class DocumentBuilder
{
    /** Sentinels for the two tokens that expand to markup instead of a value. */
    private const BLOCK_LINE_ITEMS = "\0db:line-items\0";

    private const BLOCK_TOTALS = "\0db:totals\0";

    /** Summenblock direkt hinter der Tabelle, höchstens Leerraum dazwischen. */
    private const ADJACENT_PATTERN = "/\0db:line-items\0\s*\0db:totals\0/";

    public function __construct(
        private readonly DocumentPreset $preset,
        private readonly DocumentRenderer $renderer,
        private readonly PlaceholderRenderer $placeholders,
        private readonly LineItemsRenderer $lineItems,
        private readonly TotalsRenderer $totals,
        // Optional: Ohne ihn entsteht der Beleg wie bisher, nur ohne Übertrag.
        private readonly ?PageAnalyzer $pageAnalyzer = null,
    ) {}

    /**
     * Builds the complete HTML document without rendering it. Use this for the
     * on-screen preview so preview and PDF cannot drift apart.
     *
     * @param  array<string, mixed>  $options
     */
    public function html(DocumentData $data, string $body, ?PageSetup $page = null, array $options = []): string
    {
        $page ??= PageSetup::din5008();

        // The two block tokens are parked behind sentinels first. They expand
        // to markup rather than to a value, so they must survive placeholder
        // substitution — and they must be filled in *afterwards*, otherwise a
        // line-item description containing "{{ sender.vat_id }}" would be
        // substituted along with the template's own tokens.
        $body = (string) preg_replace(
            ['/\{\{\s*line_items\s*\}\}/', '/\{\{\s*totals\s*\}\}/'],
            [self::BLOCK_LINE_ITEMS, self::BLOCK_TOTALS],
            $body,
        );

        $body = $this->placeholders->renderHtml($body, $data->placeholders());

        // Steht der Summenblock unmittelbar hinter der Tabelle, wird er zu
        // deren Schlusszeilen. Als eigener Block mit `page-break-inside: avoid`
        // springt er sonst komplett auf die nächste Seite und steht dort allein.
        $merge = $data->totals !== null && $this->totalsFollowLineItems($body);
        $totalRows = [];

        if ($merge) {
            $totalRows = $this->totals->rows($data->totals, $options);

            // Aus zwei Marken wird eine — die Summen kommen jetzt aus der Tabelle.
            $body = (string) preg_replace(self::ADJACENT_PATTERN, self::BLOCK_LINE_ITEMS, $body);
        }

        $body = str_replace(
            [self::BLOCK_LINE_ITEMS, self::BLOCK_TOTALS],
            [
                $this->lineItems->render($data->lineItems, $options, $totalRows),
                $data->totals !== null && ! $merge ? $this->totals->render($data->totals, $options) : '',
            ],
            $body,
        );

        // Kopf- und Fußzeile enthalten dieselben Platzhalter wie der Rumpf —
        // eine Fußzeile mit roher {{ sender.iban }} im PDF wäre der Fehler,
        // den der Platzhalter verhindern soll.
        foreach (['header_html', 'footer_html'] as $slot) {
            if (isset($options[$slot]) && is_string($options[$slot])) {
                $options[$slot] = $this->placeholders->renderHtml($options[$slot], $data->placeholders());
            }
        }

        return $this->preset->render($data, $body, $page, $options);
    }

    /**
     * Builds and renders the document.
     *
     * @param  array<string, mixed>  $options
     * @return string Raw PDF bytes.
     */
    public function pdf(DocumentData $data, string $body, ?PageSetup $page = null, array $options = []): string
    {
        $page ??= PageSetup::din5008();

        $pdf = $this->renderer->render($this->html($data, $body, $page, $options), $page);

        return $this->mitUebertrag($pdf, $data, $body, $page, $options);
    }

    /**
     * Zeichnet den Übertrag ein — oder gibt den Beleg unverändert zurück.
     *
     * Der Ablauf in einem Satz: Erst drucken, dann nachsehen, wo die Seiten
     * enden, dann mit vorgegebenen Umbrüchen noch einmal drucken.
     *
     * Warum überhaupt zweimal: DomPDF entscheidet die Umbrüche im Layout und
     * verrät sie nicht. Ein Übertrag braucht aber die Summe bis zum
     * Seitenende. Der erste Lauf ist also die Messung.
     *
     * Warum das nicht im Kreis läuft: Der zweite Lauf bekommt die Umbrüche
     * VORGEGEBEN (`page_breaks`), statt sie DomPDF zu überlassen — gemessen,
     * dass `page-break-after` auf einer `<tr>` beachtet wird. Damit ist die
     * Aufteilung nicht mehr Ergebnis, sondern Vorgabe, und die Rückkopplung,
     * an der ein naiver zweiter Lauf scheitert, gibt es nicht.
     *
     * Und wenn doch etwas nicht aufgeht — kein Analyzer, unklare Zuordnung,
     * eine Seite läuft trotz Reserve über —, bleibt es beim Ergebnis des ersten
     * Laufs. Der Übertrag ist eine Zugabe; er darf einen Beleg nie schlechter
     * machen als ohne ihn.
     *
     * @param  array<string, mixed>  $options
     */
    private function mitUebertrag(string $pdf, DocumentData $data, string $body, PageSetup $page, array $options): string
    {
        if (($options['carry_over'] ?? false) !== true || $this->pageAnalyzer === null) {
            return $pdf;
        }

        $positionen = array_values(array_map(
            static fn (LineItem $item): string => $item->position,
            $data->lineItems,
        ));

        $seiten = $this->pageAnalyzer->pagesByPosition($pdf, $positionen);

        if ($seiten === null || count(array_unique($seiten)) < 2) {
            return $pdf;
        }

        // Wo die Seiten HEUTE enden — die Messung.
        $grenzen = $this->seitengrenzen($seiten, $positionen);

        if ($grenzen === []) {
            return $pdf;
        }

        // Wo sie im zweiten Lauf enden SOLLEN.
        $umbrueche = $this->umbruecheMitReserve($grenzen, count($positionen));

        if ($umbrueche === []) {
            return $pdf;
        }

        $zweiter = $this->renderer->render(
            $this->html($data, $body, $page, ['page_breaks' => $umbrueche] + $options),
            $page,
        );

        // Die Gegenprobe: Halten die vorgegebenen Umbrüche? Läuft eine Seite
        // trotz der zurückgehaltenen Zeile über, bricht DomPDF zusätzlich um —
        // dann stünde ein Übertrag mitten auf der Seite.
        //
        // Verglichen wird die MESSUNG des zweiten Laufs mit der VORGABE, nicht
        // Vorgabe mit Vorgabe: Die Reduktion um eine Zeile darf nur einmal
        // stattfinden, sonst kann die Probe nie zutreffen.
        $kontrolle = $this->pageAnalyzer->pagesByPosition($zweiter, $positionen);

        if ($kontrolle === null || $this->seitengrenzen($kontrolle, $positionen) !== $umbrueche) {
            return $pdf;
        }

        return $zweiter;
    }

    /**
     * Rechnet die gemessenen Seitengrenzen in Vorgaben um, die den Übertrag
     * mittragen.
     *
     * Der Platz dafür muss von der Kapazität abgezogen werden, und zwar
     * unterschiedlich: Die erste Seite trägt nur einen Übertrag (unten), jede
     * mittlere zwei (oben und unten), die letzte einen (oben). Der naheliegende
     * Weg — von jeder gemessenen Grenze eine Zeile abziehen — geht schief, weil
     * die Abzüge sich aufsummieren: Ist Seite 1 um eine Zeile kürzer, beginnt
     * Seite 2 eine Zeile früher UND endet zwei Zeilen früher.
     *
     * @param  list<int>  $grenzen  Zeilen bis zum jeweiligen Seitenende
     * @return list<int>
     */
    private function umbruecheMitReserve(array $grenzen, int $zeilen): array
    {
        $kapazitaeten = [];
        $vorher = 0;

        foreach ($grenzen as $grenze) {
            $kapazitaeten[] = $grenze - $vorher;
            $vorher = $grenze;
        }

        // Die Kapazität einer Folgeseite — sie gilt auch für die Seiten, die
        // durch das Zurückhalten überhaupt erst entstehen.
        $folgeseite = count($kapazitaeten) > 1 ? $kapazitaeten[1] : $kapazitaeten[0];

        $umbrueche = [];
        $gesetzt = 0;
        $nummer = 0;

        // Solange Zeilen übrig sind, weiter umbrechen — auch über die gemessene
        // Seitenzahl hinaus. Genau daran scheitert die naive Rechnung: Wer nur
        // die gemessenen Grenzen um eine Zeile nach vorn zieht, schiebt den
        // zurückgehaltenen Rest auf die LETZTE Seite, und die läuft dann über.
        // Der Übertrag kostet Platz, und Platz kostet am Ende eine Seite mehr.
        while ($gesetzt < $zeilen) {
            $kapazitaet = $kapazitaeten[$nummer] ?? $folgeseite;

            // Erste Seite: nur der Übertrag am Fuß. Ab der zweiten kommt der
            // am Kopf dazu.
            $passt = $kapazitaet - ($nummer === 0 ? 1 : 2);

            // Bleibt keine Zeile übrig, trüge die Seite nur noch Überträge —
            // dann lieber ganz ohne.
            if ($passt < 1) {
                return [];
            }

            $gesetzt += $passt;
            $nummer++;

            // Nach der letzten Zeile wird nicht umgebrochen: Dort folgt der
            // Summenblock, und ein Umbruch davor ließe ihn allein stehen.
            if ($gesetzt >= $zeilen) {
                break;
            }

            $umbrueche[] = $gesetzt;

            // Reißleine gegen eine Fehlmessung, die die Schleife nie beenden
            // würde — mehr Seiten als Zeilen kann es nicht geben.
            if ($nummer > $zeilen) {
                return [];
            }
        }

        return $umbrueche;
    }

    /**
     * Wie viele Zeilen jede Seite trägt — reine Messung, ohne Reserve.
     *
     * @param  array<string, int>  $seiten  Positionsnummer → Seite
     * @param  list<string>  $positionen  in Druckreihenfolge
     * @return list<int> Anzahl Zeilen bis zum jeweiligen Seitenende
     */
    private function seitengrenzen(array $seiten, array $positionen): array
    {
        $grenzen = [];
        $vorherigeSeite = null;

        foreach ($positionen as $index => $position) {
            $seite = $seiten[$position] ?? null;

            if ($seite === null) {
                return [];
            }

            if ($vorherigeSeite !== null && $seite !== $vorherigeSeite) {
                $grenzen[] = $index;
            }

            $vorherigeSeite = $seite;
        }

        return $grenzen;
    }

    /**
     * Whether the template puts the summary straight after the item table.
     *
     * A template is free to place them apart — an outro paragraph in between is
     * a legitimate layout. Only the adjacent case gets merged, so the rule
     * stays inspectable rather than magic.
     */
    private function totalsFollowLineItems(string $body): bool
    {
        return preg_match(self::ADJACENT_PATTERN, $body) === 1;
    }

    /**
     * Placeholders a template references but the data does not cover. Call it
     * before printing rather than shipping an invoice with a blank tax number.
     *
     * @return list<string>
     */
    public function missingPlaceholders(DocumentData $data, string $body): array
    {
        return array_values(array_diff(
            $this->placeholders->missingPlaceholders($body, $data->placeholders()),
            ['line_items', 'totals'],
        ));
    }
}
