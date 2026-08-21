<?php

use Peppermint\DocumentBuilder\Contracts\DocumentPayload;
use Peppermint\DocumentBuilder\Data\DocumentData;
use Peppermint\DocumentBuilder\Data\PageSetup;
use Peppermint\DocumentBuilder\Presets\Din5008Preset;

/**
 * The seam that lets a second kind of document exist next to the business
 * letter. A preset is handed a payload; what it may assume about that payload
 * is exactly what this contract says, and no more.
 */
it('treats a business document as a payload', function (): void {
    $data = offer();

    expect($data)->toBeInstanceOf(DocumentPayload::class)
        ->and($data->type())->toBe('offer');
});

it('exposes the same placeholders through the contract', function (): void {
    /** @var DocumentPayload $payload */
    $payload = offer();

    // A preset reaches the values through the narrow contract. If these two
    // ever diverge, a preset written against the contract prints blanks while
    // the same document printed directly looks fine.
    expect($payload->placeholders())->toBe(offer()->placeholders());
});

it('tells a preset by name when it is handed the wrong shape', function (): void {
    $fremd = new class implements DocumentPayload
    {
        public function type(): string
        {
            return 'badge';
        }

        /** @return array<string, string|null> */
        public function placeholders(): array
        {
            return ['title' => 'Anna Ahlers'];
        }
    };

    // The DIN skeleton reads sender, recipient and meta — a badge has none of
    // them. Failing here, with both class names in the message, beats printing
    // a letter with an empty address field.
    expect(fn () => (new Din5008Preset)->render($fremd, '<p>x</p>', PageSetup::din5008()))
        ->toThrow(InvalidArgumentException::class, DocumentData::class);
});
