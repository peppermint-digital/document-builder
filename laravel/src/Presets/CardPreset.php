<?php

namespace Peppermint\DocumentBuilder\Presets;

use InvalidArgumentException;
use Peppermint\DocumentBuilder\Contracts\DocumentPayload;
use Peppermint\DocumentBuilder\Contracts\DocumentPreset;
use Peppermint\DocumentBuilder\Data\CardData;
use Peppermint\DocumentBuilder\Data\PageSetup;
use Peppermint\DocumentBuilder\Data\SheetSetup;

/**
 * The skeleton for cards: where each one sits on the sheet, and how big its
 * type is.
 *
 * What the preset owns is the geometry — the grid, the page breaks, the crop
 * marks. What a template owns is what stands on the card. The split is the
 * same one the DIN preset makes, for the same reason: freedom to nudge a card
 * off the cut line is not a feature.
 */
final class CardPreset implements DocumentPreset
{
    /**
     * Type scale factor, applied to the square root of the card height.
     *
     * Square root and not a straight proportion, and that is worth explaining.
     * The two badge sizes that exist in Peppermint Connect were set by hand:
     * 124mm tall with a 28pt name, 54mm tall with 18pt. A linear rule cannot
     * fit both — the small card deliberately uses proportionally larger type,
     * because below a certain size legibility stops scaling with the paper.
     *
     * Against the square root both land almost exactly:
     *
     *   √124 × 1.15 = 12.8pt base → title at 2.2em = 28.2pt (hand-set: 28)
     *   √54  × 1.15 =  8.5pt base → title at 2.2em = 18.7pt (hand-set: 18)
     *
     * This is the mechanism that lets ONE template serve both sizes. Absolute
     * point sizes in a template would force a second copy of it — which is
     * exactly the duplication this whole thing exists to remove.
     */
    private const SCHRIFT_FAKTOR = 1.15;

    /** Title size, in multiples of the base — derived above from both real badges. */
    private const TITEL_EM = 2.2;

    /** Length of a crop mark, and how far it sits from the card. */
    private const MARKE_LAENGE = 3.0;

    private const MARKE_ABSTAND = 1.5;

    public function __construct(private readonly SheetSetup $sheet) {}

    public function name(): string
    {
        return 'card';
    }

    /**
     * The base font size for this sheet's cards, in points.
     */
    public function basisSchriftgroesse(): float
    {
        return round(sqrt($this->sheet->cardHeight) * self::SCHRIFT_FAKTOR, 1);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function css(PageSetup $page, array $options = []): string
    {
        $basis = $this->basisSchriftgroesse();

        // Flat CSS only: DomPDF runs with `default_media_type = screen`, so
        // `@media print` never applies, and it supports neither flexbox nor
        // grid. The grid here is absolute positioning in millimetres.
        $skelett = <<<CSS
        @page {
            size: {$page->paper} {$page->orientation};
            margin: {$page->marginTop}mm {$page->marginRight}mm {$page->marginBottom}mm {$page->marginLeft}mm;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: DejaVu Sans, sans-serif;
        }

        .db-sheet {
            position: relative;
            width: {$this->sheet->printableWidth()}mm;
            height: {$this->sheet->printableHeight()}mm;
        }

        .db-sheet + .db-sheet {
            page-break-before: always;
        }

        .db-card {
            position: absolute;
            width: {$this->sheet->cardWidth}mm;
            height: {$this->sheet->cardHeight}mm;
            overflow: hidden;
            font-size: {$basis}pt;
            line-height: 1.35;
        }

        .db-card-title {
            font-size: {$this->titelEm()}em;
            font-weight: bold;
            line-height: 1.15;
        }

        .db-card-subtitle {
            font-size: 1.15em;
        }

        .db-card-rows {
            font-size: 0.9em;
        }

        .db-card-code {
            text-align: center;
        }

        .db-mark {
            position: absolute;
            border-top: 0.2mm solid #000;
            width: {$this->markeLaenge()}mm;
        }
        CSS;

        // Das Aussehen eines Entwurfs — Farben, Rahmen, Abstaende — kommt vom
        // Aufrufer und wird EINMAL je Bogen angehaengt, nicht je Karte. Ein
        // Stilblock im Kartenrumpf waere bei zweihundert Namensschildern
        // zweihundertmal im PDF.
        //
        // Nach dem Skelett, damit ein Entwurf gezielt ueberschreiben kann,
        // was er anders braucht.
        $eigen = $options['card_css'] ?? null;

        return is_string($eigen) && trim($eigen) !== ''
            ? $skelett."\n".$eigen
            : $skelett;
    }

    /**
     * One card as a complete document — the degenerate sheet.
     *
     * @param  array<string, mixed>  $options
     */
    public function render(DocumentPayload $data, string $body, PageSetup $page, array $options = []): string
    {
        if (! $data instanceof CardData) {
            throw new InvalidArgumentException(sprintf(
                '%s needs %s, got %s.',
                self::class,
                CardData::class,
                $data::class,
            ));
        }

        return $this->renderSheet([$body], $page, $options);
    }

    /**
     * A stack of already-substituted card bodies, laid out across as many
     * sheets as they need.
     *
     * @param  list<string>  $bodies
     * @param  array<string, mixed>  $options
     */
    public function renderSheet(array $bodies, PageSetup $page, array $options = []): string
    {
        $seiten = $this->sheet->chunk($bodies);
        $html = '';

        foreach ($seiten as $karten) {
            $html .= '<div class="db-sheet">';

            foreach ($karten as $index => $karte) {
                $html .= $this->platziert($karte, $index);
            }

            $html .= '</div>';
        }

        $css = $this->css($page, $options);

        return <<<HTML
        <!DOCTYPE html>
        <html lang="de">
        <head>
            <meta charset="utf-8">
            <style>{$css}</style>
        </head>
        <body>{$html}</body>
        </html>
        HTML;
    }

    /**
     * One card at its place in the grid.
     */
    private function platziert(string $body, int $index): string
    {
        $spalte = $index % $this->sheet->columns;
        $zeile = intdiv($index, $this->sheet->columns);

        $left = round($spalte * ($this->sheet->cardWidth + $this->sheet->gutterX), 2);
        $top = round($zeile * ($this->sheet->cardHeight + $this->sheet->gutterY), 2);

        $marken = $this->sheet->cropMarks ? $this->marken($left, $top) : '';

        return sprintf(
            '<div class="db-card" style="left: %smm; top: %smm;">%s</div>%s',
            $left,
            $top,
            $body,
            $marken,
        );
    }

    /**
     * Crop marks above and below a card.
     *
     * Outside the card, not on it: a mark drawn inside would be cut off along
     * with the edge it is meant to show.
     */
    private function marken(float $left, float $top): string
    {
        $unten = round($top + $this->sheet->cardHeight + self::MARKE_ABSTAND, 2);
        $oben = round($top - self::MARKE_ABSTAND, 2);

        return sprintf(
            '<div class="db-mark" style="left: %smm; top: %smm;"></div>'
            .'<div class="db-mark" style="left: %smm; top: %smm;"></div>',
            $left,
            max(0.0, $oben),
            $left,
            $unten,
        );
    }

    private function titelEm(): float
    {
        return self::TITEL_EM;
    }

    private function markeLaenge(): float
    {
        return self::MARKE_LAENGE;
    }
}
