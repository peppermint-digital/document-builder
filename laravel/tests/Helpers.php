<?php

use Peppermint\DocumentBuilder\Data\DocumentData;

/**
 * A minimal but complete offer, used wherever a test needs real data rather
 * than the shape of it.
 *
 * @param  array<string, mixed>  $overrides
 */
function offer(array $overrides = []): DocumentData
{
    return DocumentData::fromArray(array_replace_recursive([
        'type' => 'offer',
        'subject' => 'Angebot über Druckerzeugnisse',
        'sender' => [
            'name' => 'Peppermint Digital GmbH',
            'lines' => ['Musterstraße 1', '26123 Oldenburg'],
        ],
        'recipient' => [
            'name' => 'Mustermann & Sohn GmbH',
            'lines' => ['Beispielweg 42', '26123 Oldenburg'],
            'note' => 'Einschreiben',
        ],
        'meta' => [
            'Angebotsnummer' => 'AN-2026-0815',
            'Datum' => '31.07.2026',
        ],
        'line_items' => [
            [
                'position' => '1',
                'description' => 'Digitaldruck, 4/4-farbig',
                'note' => '135 g/m² Bilderdruck matt',
                'quantity' => 2,
                'unit' => 'Stk',
                'unit_price' => 12.5,
                'total' => 25.0,
            ],
        ],
        'totals' => [
            'net' => 25.0,
            'gross' => 29.75,
            'taxes' => [['label' => 'zzgl. 19 % USt.', 'amount' => 4.75]],
        ],
    ], $overrides));
}
