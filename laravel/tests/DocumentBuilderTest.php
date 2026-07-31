<?php

use Peppermint\DocumentBuilder\DocumentBuilder;
use Peppermint\DocumentBuilder\Presets\Din5008Preset;
use Peppermint\DocumentBuilder\Renderers\DomPdfRenderer;
use Peppermint\DocumentBuilder\Services\LineItemsRenderer;
use Peppermint\DocumentBuilder\Services\PlaceholderRenderer;
use Peppermint\DocumentBuilder\Services\TotalsRenderer;

function builder(): DocumentBuilder
{
    return new DocumentBuilder(
        preset: new Din5008Preset,
        renderer: new DomPdfRenderer,
        placeholders: new PlaceholderRenderer,
        lineItems: new LineItemsRenderer,
        totals: new TotalsRenderer,
    );
}

it('absorbs the summary into the table when the template puts them together', function (): void {
    // A standalone summary block jumps to the next page whole when it does not
    // fit, and lands alone on an empty last sheet. As trailing rows it flows
    // with the items before it.
    $html = builder()->html(offer(), '{{ line_items }}{{ totals }}');

    expect($html)->toContain('<table class="db-line-items">')
        ->and($html)->toContain('<tbody class="db-totals-rows">')
        ->and($html)->not->toContain('<table class="db-totals">')
        ->and($html)->toContain('Gesamtbetrag')
        ->and($html)->not->toContain('{{');
});

it('keeps the summary standalone when the template separates the two', function (): void {
    // Placing an outro between them is a legitimate layout, so the merge only
    // applies to the adjacent case — the rule stays inspectable.
    $html = builder()->html(offer(), '{{ line_items }}<p>Vielen Dank.</p>{{ totals }}');

    expect($html)->toContain('<table class="db-totals">')
        ->and($html)->not->toContain('<tbody class="db-totals-rows">');
});

it('spans the summary label across every column but the last', function (): void {
    $html = builder()->html(offer(), '{{ line_items }}{{ totals }}');

    // Sechs Standardspalten -> Label über fünf, Betrag in der letzten.
    expect($html)->toContain('colspan="5"');
});

it('substitutes value placeholders and escapes them', function (): void {
    $data = offer(['custom' => ['greeting' => 'Müller & Sohn']]);

    $html = builder()->html($data, '<p>{{ greeting }}</p>');

    expect($html)->toContain('Müller &amp; Sohn');
});

it('does not substitute placeholder-looking text inside customer data', function (): void {
    // A line-item description is customer data. If it were substituted along
    // with the template's own tokens, a description could print the sender's
    // tax number.
    $data = offer([
        'line_items' => [['position' => '1', 'description' => 'Leak {{ Angebotsnummer }}']],
    ]);

    $html = builder()->html($data, '{{ line_items }}');

    expect($html)->toContain('Leak {{ Angebotsnummer }}')
        ->and($html)->not->toContain('Leak AN-2026-0815');
});

it('reports placeholders the data does not cover', function (): void {
    $missing = builder()->missingPlaceholders(offer(), '{{ line_items }}{{ totals }}{{ unknown_field }}');

    // The two block tokens are not gaps — they are handled separately.
    expect($missing)->toBe(['unknown_field']);
});

it('renders a real PDF through the DomPDF driver', function (): void {
    $pdf = builder()->pdf(offer(), '{{ line_items }}{{ totals }}');

    expect($pdf)->toStartWith('%PDF-')
        ->and(strlen($pdf))->toBeGreaterThan(1000);
});
