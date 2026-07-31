<?php

namespace Peppermint\DocumentBuilder\Data;

/**
 * The summary block below the line items.
 *
 * The package never calculates anything — VAT rules, rounding and reverse
 * charge are the host's business. It only lays out what it is handed.
 */
class Totals
{
    /**
     * @param  list<array{label: string, amount: float}>  $additional  Extra rows such as discounts or shipping.
     * @param  list<array{label: string, amount: float}>  $taxes  One row per tax rate.
     */
    public function __construct(
        public readonly float $net = 0.0,
        public readonly float $gross = 0.0,
        public readonly array $taxes = [],
        public readonly array $additional = [],
        public readonly string $currency = 'EUR',
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function fromArray(array $attributes): self
    {
        /** @var list<array{label: string, amount: float}> $taxes */
        $taxes = $attributes['taxes'] ?? [];
        /** @var list<array{label: string, amount: float}> $additional */
        $additional = $attributes['additional'] ?? [];

        return new self(
            net: (float) ($attributes['net'] ?? 0),
            gross: (float) ($attributes['gross'] ?? 0),
            taxes: $taxes,
            additional: $additional,
            currency: (string) ($attributes['currency'] ?? 'EUR'),
        );
    }
}
