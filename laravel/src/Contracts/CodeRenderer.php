<?php

namespace Peppermint\DocumentBuilder\Contracts;

use Peppermint\DocumentBuilder\Data\CardCode;

/**
 * Turns a card's code into something a template can place.
 *
 * A driver, for the same reason `DocumentRenderer` is one: the package should
 * not force a QR library on a host that never prints one, and a host that
 * already has its own should be able to keep it. The bundled driver uses
 * whatever of `endroid/qr-code` and `picqer/php-barcode-generator` is
 * installed — both are suggestions, not requirements.
 */
interface CodeRenderer
{
    /**
     * A `data:` URI ready to drop into an `<img src="…">`.
     *
     * Deliberately a data URI and not a path: the PDF renderer must be able to
     * resolve the image without a filesystem or a web server in between, and a
     * badge sheet printed from a queue worker has neither.
     */
    public function dataUri(CardCode $code): string;

    /**
     * Whether this driver can run here. Lets a host say "codes are off" with a
     * clear message instead of printing an empty square.
     */
    public function supports(CardCode $code): bool;
}
