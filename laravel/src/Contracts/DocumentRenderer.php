<?php

namespace Peppermint\DocumentBuilder\Contracts;

use Peppermint\DocumentBuilder\Data\PageSetup;

/**
 * Turns a finished HTML document into PDF bytes.
 *
 * Kept as a driver so the engine stays swappable. DomPDF renders the bundled
 * DIN 5008 preset faithfully today, but a host that needs modern CSS can drop
 * in a headless browser driver without touching a single template.
 */
interface DocumentRenderer
{
    /**
     * @param  string  $html  A complete HTML document, already placeholder-substituted.
     * @return string Raw PDF bytes.
     */
    public function render(string $html, PageSetup $page): string;

    /**
     * Whether this driver can run in the current environment. Lets a host fall
     * back or fail with a clear message instead of a stack trace.
     */
    public function isAvailable(): bool;
}
