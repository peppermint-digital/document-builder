<?php

namespace Peppermint\DocumentBuilder\Data;

/**
 * A sender or recipient.
 *
 * `lines` is kept as an ordered list rather than street/zip/city fields so
 * that international addresses, care-of lines and department lines survive
 * without the package inventing a schema for every country.
 */
class Party
{
    /**
     * @param  list<string>  $lines  Address block, one entry per printed line.
     * @param  array<string, string|null>  $meta  Extra values such as vat_id or customer_number.
     */
    public function __construct(
        public readonly string $name = '',
        public readonly array $lines = [],
        public readonly ?string $note = null,
        public readonly array $meta = [],
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function fromArray(array $attributes): self
    {
        /** @var list<string> $lines */
        $lines = array_values(array_filter(
            array_map(static fn ($line): string => trim((string) $line), $attributes['lines'] ?? []),
            static fn (string $line): bool => $line !== '',
        ));

        /** @var array<string, string|null> $meta */
        $meta = $attributes['meta'] ?? [];

        return new self(
            name: (string) ($attributes['name'] ?? ''),
            lines: $lines,
            note: isset($attributes['note']) ? (string) $attributes['note'] : null,
            meta: $meta,
        );
    }

    /**
     * The full address block, name first.
     *
     * @return list<string>
     */
    public function addressLines(): array
    {
        return $this->name === '' ? $this->lines : [$this->name, ...$this->lines];
    }
}
