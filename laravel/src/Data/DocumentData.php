<?php

namespace Peppermint\DocumentBuilder\Data;

use Peppermint\DocumentBuilder\Services\LineItemsRenderer;
use Peppermint\DocumentBuilder\Services\PlaceholderRenderer;

/**
 * Everything a document needs to be printed — the contract between a host
 * application and this package.
 *
 * A host maps its own models onto this shape once; from then on every preset,
 * renderer and template works without knowing anything about that host. This
 * is what makes a second consumer cheap.
 */
class DocumentData
{
    /**
     * @param  list<LineItem>  $lineItems
     * @param  array<string, string|null>  $meta  The info block, e.g. document number, date, customer number.
     * @param  array<string, string|null>  $custom  Free placeholders a template may reference.
     */
    public function __construct(
        public readonly string $type,
        public readonly Party $sender,
        public readonly Party $recipient,
        public readonly string $subject = '',
        public readonly ?string $intro = null,
        public readonly ?string $outro = null,
        public readonly array $lineItems = [],
        public readonly ?Totals $totals = null,
        public readonly array $meta = [],
        public readonly array $custom = [],
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function fromArray(array $attributes): self
    {
        /** @var array<string, string|null> $meta */
        $meta = $attributes['meta'] ?? [];
        /** @var array<string, string|null> $custom */
        $custom = $attributes['custom'] ?? [];

        return new self(
            type: (string) ($attributes['type'] ?? 'document'),
            sender: Party::fromArray($attributes['sender'] ?? []),
            recipient: Party::fromArray($attributes['recipient'] ?? []),
            subject: (string) ($attributes['subject'] ?? ''),
            intro: isset($attributes['intro']) ? (string) $attributes['intro'] : null,
            outro: isset($attributes['outro']) ? (string) $attributes['outro'] : null,
            lineItems: LineItem::collection($attributes['line_items'] ?? []),
            totals: isset($attributes['totals']) ? Totals::fromArray($attributes['totals']) : null,
            meta: $meta,
            custom: $custom,
        );
    }

    /**
     * Flat `{{ key }}` replacements for {@see PlaceholderRenderer}.
     *
     * Line items are not flattened here on purpose — they are laid out by
     * {@see LineItemsRenderer}, because a
     * table of unknown length is not a placeholder.
     *
     * @return array<string, string|null>
     */
    public function placeholders(): array
    {
        $flat = [
            'subject' => $this->subject,
            'intro' => $this->intro,
            'outro' => $this->outro,
            'sender.name' => $this->sender->name,
            'sender.address' => implode("\n", $this->sender->addressLines()),
            'recipient.name' => $this->recipient->name,
            'recipient.address' => implode("\n", $this->recipient->addressLines()),
        ];

        foreach ($this->sender->meta as $key => $value) {
            $flat['sender.'.$key] = $value;
        }

        foreach ($this->recipient->meta as $key => $value) {
            $flat['recipient.'.$key] = $value;
        }

        foreach ($this->meta as $key => $value) {
            $flat[$key] = $value;
        }

        foreach ($this->custom as $key => $value) {
            $flat[$key] = $value;
        }

        return $flat;
    }
}
