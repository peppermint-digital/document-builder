<?php

namespace Peppermint\DocumentBuilder;

use Peppermint\DocumentBuilder\Contracts\CodeRenderer;
use Peppermint\DocumentBuilder\Contracts\DocumentRenderer;
use Peppermint\DocumentBuilder\Data\CardCode;
use Peppermint\DocumentBuilder\Data\CardData;
use Peppermint\DocumentBuilder\Data\SheetSetup;
use Peppermint\DocumentBuilder\Presets\CardPreset;
use Peppermint\DocumentBuilder\Services\PlaceholderRenderer;

/**
 * The entry point for cards — badges, tickets, place cards.
 *
 * Next to `DocumentBuilder`, not inside it. The business builder carries rules
 * that only make sense for an invoice: it expands `{{ line_items }}`, and it
 * knows that a totals block directly after the item table becomes that table's
 * closing rows. Making those steps swappable would buy an abstraction for a
 * case that will never use it. Both builders share what is genuinely common —
 * placeholder substitution and the PDF driver.
 */
class CardBuilder
{
    public function __construct(
        private readonly DocumentRenderer $renderer,
        private readonly PlaceholderRenderer $placeholders,
        private readonly ?CodeRenderer $codes = null,
    ) {}

    /**
     * Builds the complete HTML sheet without rendering it. Use this for the
     * on-screen preview so preview and PDF cannot drift apart.
     *
     * @param  list<CardData>  $cards
     * @param  array<string, mixed>  $options
     */
    public function html(array $cards, string $body, SheetSetup $sheet, array $options = []): string
    {
        $preset = new CardPreset($sheet);

        $bodies = array_map(
            fn (CardData $card): string => $this->karte($card, $body),
            array_values($cards),
        );

        return $preset->renderSheet($bodies, $sheet->page, $options);
    }

    /**
     * Builds and renders the sheet.
     *
     * @param  list<CardData>  $cards
     * @param  array<string, mixed>  $options
     * @return string Raw PDF bytes.
     */
    public function pdf(array $cards, string $body, SheetSetup $sheet, array $options = []): string
    {
        return $this->renderer->render($this->html($cards, $body, $sheet, $options), $sheet->page);
    }

    /**
     * Placeholders a template references but this card does not cover.
     *
     * Call it before printing three hundred badges with a blank line where the
     * company should have been.
     *
     * @return list<string>
     */
    public function missingPlaceholders(CardData $card, string $body): array
    {
        return array_values(array_diff(
            $this->placeholders->missingPlaceholders($body, $card->placeholders()),
            ['code_image'],
        ));
    }

    /**
     * One card's body, substituted.
     */
    private function karte(CardData $card, string $body): string
    {
        // The code token is parked behind a sentinel first: it expands to
        // markup, not to a value, so it has to survive placeholder
        // substitution — and it must be filled in afterwards, or a card whose
        // own text happens to contain "{{ code_image }}" would grow a second
        // code.
        $marken = [];

        // Der Hauptcode und jeder benannte Zusatzcode bekommen eine eigene
        // Marke. Ein Namensschild mit QR je Workshop braucht mehrere Bilder
        // auf derselben Karte — mit nur einem Platzhalter muesste die Vorlage
        // dafuer wieder aufgeteilt werden, und genau davon kommen wir her.
        $body = (string) preg_replace_callback(
            '/\{\{\s*code_image(?:\.([^}\s]+))?\s*\}\}/',
            function (array $treffer) use ($card, &$marken): string {
                $schluessel = $treffer[1] ?? null;
                $code = $schluessel === null ? $card->code : ($card->codes[$schluessel] ?? null);
                $marke = "\0db:code:".count($marken)."\0";
                $marken[$marke] = $this->codeBild($code);

                return $marke;
            },
            $body,
        );

        $body = $this->placeholders->renderHtml($body, $card->placeholders());

        return strtr($body, $marken);
    }

    /**
     * The code as an `<img>`, or nothing at all.
     *
     * A card without a code, or a driver that cannot make one, prints no
     * image. An empty box would look like a code that failed to scan — worse
     * than an honest gap, because someone would try to scan it.
     */
    private function codeBild(?CardCode $code): string
    {
        if ($code === null || $this->codes === null || ! $this->codes->supports($code)) {
            return '';
        }

        // A QR code is square. A linear barcode is not: `size` is its height,
        // and its width follows from the content. Forcing that one square
        // squeezes the bars until a scanner loses them.
        $masse = $code->istStrichcode()
            ? sprintf('height: %smm;', $code->size)
            : sprintf('width: %smm; height: %smm;', $code->size, $code->size);

        return sprintf(
            '<img src="%s" alt="" style="%s">',
            $this->codes->dataUri($code),
            $masse,
        );
    }
}
