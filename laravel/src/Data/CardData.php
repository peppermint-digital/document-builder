<?php

namespace Peppermint\DocumentBuilder\Data;

use Peppermint\DocumentBuilder\Contracts\DocumentPayload;

/**
 * One card — a badge, a ticket, a place card.
 *
 * The second shape next to `DocumentData`, and deliberately not a subset of
 * it: a card has no sender, no recipient, no line items and no totals. What it
 * has is a headline, some supporting lines and usually something to scan.
 *
 * The vocabulary stays domain-free on purpose. There is no `participant` and
 * no `workshop` here — the moment the package learns about events, the second
 * consumer becomes expensive again, which is the whole thing this shape is
 * meant to prevent. A conference badge maps onto `title` and `rows`; so does a
 * cloakroom stub.
 */
final class CardData implements DocumentPayload
{
    /**
     * @param  array<string, string|null>  $rows  Supporting lines, label => value.
     * @param  array<string, string|null>  $meta  Not for printing: sorting, grouping, filenames.
     * @param  array<string, string|null>  $custom  Free placeholders a template may reference.
     */
    public function __construct(
        public readonly string $type,
        public readonly string $title,
        public readonly ?string $subtitle = null,
        public readonly array $rows = [],
        public readonly ?CardCode $code = null,
        public readonly array $codes = [],
        public readonly array $images = [],
        public readonly array $meta = [],
        public readonly array $custom = [],
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function fromArray(array $attributes): self
    {
        /** @var array<string, string|null> $rows */
        $rows = $attributes['rows'] ?? [];
        /** @var array<string, string|null> $meta */
        $meta = $attributes['meta'] ?? [];
        /** @var array<string, string|null> $custom */
        $custom = $attributes['custom'] ?? [];

        return new self(
            type: (string) ($attributes['type'] ?? 'card'),
            title: (string) ($attributes['title'] ?? ''),
            subtitle: isset($attributes['subtitle']) ? (string) $attributes['subtitle'] : null,
            rows: $rows,
            code: isset($attributes['code']) && is_array($attributes['code'])
                ? CardCode::fromArray($attributes['code'])
                : null,
            codes: array_map(
                CardCode::fromArray(...),
                array_filter((array) ($attributes['codes'] ?? []), is_array(...)),
            ),
            images: array_map(strval(...), (array) ($attributes['images'] ?? [])),
            meta: $meta,
            custom: $custom,
        );
    }

    public function type(): string
    {
        return $this->type;
    }

    /**
     * Everything a template may reference as `{{ … }}`.
     *
     * The supporting lines are reachable two ways: by their own label, so a
     * template can place a known one exactly (`{{ row.Firma }}`), and as a
     * block for the ones a template cannot know in advance. Without the second
     * every new field would need a template change; without the first the
     * layout could never single one out.
     *
     * @return array<string, string|null>
     */
    public function placeholders(): array
    {
        $werte = [
            'type' => $this->type,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'code' => $this->code?->value,
        ];

        foreach ($this->rows as $label => $wert) {
            $werte['row.'.$label] = $wert === null ? null : (string) $wert;
        }

        foreach ($this->custom as $schluessel => $wert) {
            $werte[(string) $schluessel] = $wert === null ? null : (string) $wert;
        }

        return $werte;
    }

    /**
     * The supporting lines that actually carry something.
     *
     * A badge with an empty company line should print a shorter badge, not a
     * gap where the company would have been.
     *
     * @return array<string, string>
     */
    public function gefuellteZeilen(): array
    {
        $gefuellt = [];

        foreach ($this->rows as $label => $wert) {
            if (trim((string) $wert) !== '') {
                $gefuellt[(string) $label] = (string) $wert;
            }
        }

        return $gefuellt;
    }
}
