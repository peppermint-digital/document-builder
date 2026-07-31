<?php

namespace Peppermint\DocumentBuilder;

use Peppermint\DocumentBuilder\Contracts\DocumentPreset;
use Peppermint\DocumentBuilder\Contracts\DocumentRenderer;
use Peppermint\DocumentBuilder\Data\DocumentData;
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

        return $this->renderer->render($this->html($data, $body, $page, $options), $page);
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
