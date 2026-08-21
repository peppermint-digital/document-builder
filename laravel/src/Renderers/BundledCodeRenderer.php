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
        if ($code->kind === CardCode::QR) {
            return class_exists(QrCode::class);
        }

        return $code->istStrichcode() && class_exists(BarcodeGeneratorPNG::class);
    }

    public function dataUri(CardCode $code): string
    {
        if (! $this->supports($code)) {
            throw new RuntimeException(sprintf(
                'No driver for code kind "%s". Install %s.',
                $code->kind,
                $code->istStrichcode() ? 'picqer/php-barcode-generator' : 'endroid/qr-code',
            ));
        }

        return $code->kind === CardCode::QR
            ? $this->qr($code)
            : $this->strichcode($code);
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

    private function strichcode(CardCode $code): string
    {
        // `size` is the bar height; the width follows from the content and
        // must not be squeezed, or scanners lose it.
        $png = (new BarcodeGeneratorPNG)->getBarcode(
            $this->inhaltFuer($code),
            match ($code->kind) {
                CardCode::CODE39 => BarcodeGeneratorPNG::TYPE_CODE_39,
                CardCode::EAN13 => BarcodeGeneratorPNG::TYPE_EAN_13,
                default => BarcodeGeneratorPNG::TYPE_CODE_128,
            },
            2,
            (int) round($code->size * self::PIXEL_JE_MM),
        );

        return 'data:image/png;base64,'.base64_encode($png);
    }

    /**
     * The value in the form the chosen kind can actually encode.
     *
     * CODE39 has no lower case, and EAN-13 is exactly twelve digits plus a
     * check digit the generator adds. Handing either the raw value throws deep
     * inside the library with a message about the library, not about the badge
     * — so the shaping happens here, where the reason is visible.
     */
    private function inhaltFuer(CardCode $code): string
    {
        return match ($code->kind) {
            CardCode::CODE39 => strtoupper($code->value),
            CardCode::EAN13 => str_pad(
                substr(preg_replace('/\D/', '', $code->value) ?? '', 0, 12),
                12,
                '0',
                STR_PAD_LEFT,
            ),
            default => $code->value,
        };
    }
}
