<?php

namespace Peppermint\DocumentBuilder\Data;

use InvalidArgumentException;

/**
 * The machine-readable mark on a card.
 *
 * It lives in the package because both kinds of card need it and neither wants
 * to own it: a badge is scanned at check-in, a ticket at the door. Left to the
 * consumer it ends up next to the template, which is where it sat in Peppermint
 * Connect — close enough to the markup to be copied along with it.
 */
final class CardCode
{
    public const QR = 'qr';

    public const CODE128 = 'code128';

    public const ARTEN = [self::QR, self::CODE128];

    /**
     * @param  string  $value  What a scanner should read — usually an opaque token, not a name.
     * @param  string  $kind  One of self::ARTEN.
     * @param  float  $size  Edge length in millimetres. For CODE128 this is the height.
     */
    public function __construct(
        public readonly string $value,
        public readonly string $kind = self::QR,
        public readonly float $size = 20.0,
    ) {
        if (trim($value) === '') {
            throw new InvalidArgumentException('A code without a value would print an empty box.');
        }

        if (! in_array($kind, self::ARTEN, true)) {
            throw new InvalidArgumentException(sprintf(
                'Unknown code kind "%s". Known: %s.',
                $kind,
                implode(', ', self::ARTEN),
            ));
        }

        if ($size <= 0) {
            throw new InvalidArgumentException('A code needs a size greater than zero.');
        }
    }

    /**
     * @param  array{value?: string, kind?: string, size?: float|int}  $attributes
     */
    public static function fromArray(array $attributes): self
    {
        return new self(
            value: (string) ($attributes['value'] ?? ''),
            kind: (string) ($attributes['kind'] ?? self::QR),
            size: (float) ($attributes['size'] ?? 20.0),
        );
    }
}
