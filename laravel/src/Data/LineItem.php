<?php

namespace Peppermint\DocumentBuilder\Data;

/**
 * One row of the line-item table.
 *
 * Deliberately a plain value object rather than an interface onto a host
 * model: the host maps whatever it has onto this shape, and the package never
 * learns what an "order position" means in that application.
 */
class LineItem
{
    /**
     * @param  array<string, scalar|null>  $extra  Additional values addressable as custom columns.
     */
    public function __construct(
        public readonly string $position,
        public readonly string $description,
        public readonly ?string $note = null,
        public readonly ?float $quantity = null,
        public readonly ?string $unit = null,
        public readonly ?float $unitPrice = null,
        public readonly ?float $total = null,
        public readonly array $extra = [],
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function fromArray(array $attributes): self
    {
        $known = ['position', 'description', 'note', 'quantity', 'unit', 'unit_price', 'total'];

        return new self(
            position: (string) ($attributes['position'] ?? ''),
            description: (string) ($attributes['description'] ?? ''),
            note: isset($attributes['note']) ? (string) $attributes['note'] : null,
            quantity: isset($attributes['quantity']) ? (float) $attributes['quantity'] : null,
            unit: isset($attributes['unit']) ? (string) $attributes['unit'] : null,
            unitPrice: isset($attributes['unit_price']) ? (float) $attributes['unit_price'] : null,
            total: isset($attributes['total']) ? (float) $attributes['total'] : null,
            extra: array_diff_key($attributes, array_flip($known)),
        );
    }

    /**
     * @param  iterable<array<string, mixed>|self>  $rows
     * @return list<self>
     */
    public static function collection(iterable $rows): array
    {
        $items = [];

        foreach ($rows as $row) {
            $items[] = $row instanceof self ? $row : self::fromArray($row);
        }

        return $items;
    }

    /**
     * Raw value for a column key, including anything passed through `extra`.
     */
    public function value(string $key): string|float|int|bool|null
    {
        return match ($key) {
            'position' => $this->position,
            'description' => $this->description,
            'note' => $this->note,
            'quantity' => $this->quantity,
            'unit' => $this->unit,
            'unit_price' => $this->unitPrice,
            'total' => $this->total,
            default => $this->extra[$key] ?? null,
        };
    }
}
