<?php

namespace Peppermint\DocumentBuilder\Contracts;

/**
 * What a preset is handed to print.
 *
 * There is more than one shape of document. A business letter has a sender, a
 * recipient, line items and totals; a badge has a name, a code and a couple of
 * lines. Squeezing the second into the first would leave every consumer to
 * work out by convention which half of the fields is meaningless — and the
 * promise this package makes ("map your models onto this shape once and every
 * preset works") only holds while the shape actually fits.
 *
 * So the shapes stay apart, and this is the little they have in common: a name
 * for the kind of document, and the values a template may reference. A preset
 * that needs more narrows the type itself.
 */
interface DocumentPayload
{
    /**
     * The kind of document, e.g. `invoice`, `badge`, `ticket`.
     */
    public function type(): string;

    /**
     * Everything a template may reference as `{{ … }}`.
     *
     * @return array<string, string|null>
     */
    public function placeholders(): array;
}
