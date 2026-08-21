<?php

namespace Peppermint\DocumentBuilder\Renderers;

use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Peppermint\DocumentBuilder\Contracts\CodeRenderer;
use Peppermint\DocumentBuilder\Data\CardCode;
use Picqer\Barcode\BarcodeGeneratorPNG;
use RuntimeException;

/**
 * The default code driver: `endroid/qr-code` for QR, `picqer/php-barcode-generator`
 * for CODE128.
 *
 * Both are suggestions rather than requirements, so every entry point checks
 * whether the class is actually there. A host that prints no codes carries no
 * extra dependency; a host that does almost certainly has these two already.
 */
final class BundledCodeRenderer implements CodeRenderer
{
    /**
     * Pixels per millimetre. 8 lands a 20mm QR at 160px, which prints cleanly
     * at 300dpi without bloating the PDF — a badge sheet holds dozens of these
     * and each one is embedded in full.
     */
    private const PIXEL_JE_MM = 8;

    public function supports(CardCode $code): bool
    {
        return match ($code->kind) {
            CardCode::QR => class_exists(QrCode::class),
            CardCode::CODE128 => class_exists(BarcodeGeneratorPNG::class),
            default => false,
        };
    }

    public function dataUri(CardCode $code): string
    {
        if (! $this->supports($code)) {
            throw new RuntimeException(sprintf(
                'No driver for code kind "%s". Install %s.',
                $code->kind,
                $code->kind === CardCode::CODE128 ? 'picqer/php-barcode-generator' : 'endroid/qr-code',
            ));
        }

        return match ($code->kind) {
            CardCode::QR => $this->qr($code),
            CardCode::CODE128 => $this->code128($code),
            default => throw new RuntimeException('Unreachable: supports() already covered this.'),
        };
    }

    private function qr(CardCode $code): string
    {
        $qr = new QrCode(
            data: $code->value,
            encoding: new Encoding('UTF-8'),
            size: (int) round($code->size * self::PIXEL_JE_MM),

            // No quiet zone from the library: the template controls the space
            // around the code, and a second margin baked into the image makes
            // the printed square smaller than the millimetres asked for.
            margin: 0,
        );

        return (new PngWriter)->write($qr)->getDataUri();
    }

    private function code128(CardCode $code): string
    {
        // `size` is the bar height for a linear code; the width follows from
        // the content and must not be squeezed, or scanners lose it.
        $png = (new BarcodeGeneratorPNG)->getBarcode(
            $code->value,
            BarcodeGeneratorPNG::TYPE_CODE_128,
            2,
            (int) round($code->size * self::PIXEL_JE_MM),
        );

        return 'data:image/png;base64,'.base64_encode($png);
    }
}
