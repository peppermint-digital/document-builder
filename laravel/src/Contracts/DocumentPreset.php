<?php

namespace Peppermint\DocumentBuilder\Contracts;

use Peppermint\DocumentBuilder\Data\PageSetup;

/**
 * A document skeleton: the parts a user must not be able to delete.
 *
 * A preset owns the page geometry, the address field, the header and footer
 * and the mandatory legal details. The builder only fills the free zones. This
 * split is deliberate — on an invoice, freedom to move the tax number is a
 * liability, not a feature.
 */
interface DocumentPreset
{
    /**
     * Machine name, e.g. `din5008`.
     */
    public function name(): string;

    /**
     * Print CSS for this skeleton. Must be flat CSS: DomPDF is configured with
     * `default_media_type = screen`, so `@media print` blocks never apply, and
     * it supports neither flexbox nor grid.
     *
     * @param  array<string, mixed>  $options
     */
    public function css(PageSetup $page, array $options = []): string;

    /**
     * Composes the complete HTML document from the skeleton and the body that
     * the builder produced for the free zones.
     *
     * @param  array<string, mixed>  $options
     */
    public function render(DocumentPayload $data, string $body, PageSetup $page, array $options = []): string;
}
