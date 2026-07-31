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

        $body = str_replace(
            [self::BLOCK_LINE_ITEMS, self::BLOCK_TOTALS],
            [
                $this->lineItems->render($data->lineItems, $options),
                $data->totals !== null ? $this->totals->render($data->totals, $options) : '',
            ],
            $body,
        );

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
