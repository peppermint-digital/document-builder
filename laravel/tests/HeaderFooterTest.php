<?php

use Peppermint\DocumentBuilder\Data\PageSetup;
use Peppermint\DocumentBuilder\Presets\Din5008Preset;

it('renders the template header above the address field', function (): void {
    $html = (new Din5008Preset)->render(offer(), '', PageSetup::din5008(), [
        'header_html' => '<p>Peppermint Digital — Druck &amp; Medien</p>',
    ]);

    expect($html)->toContain('class="db-header"')
        ->and($html)->toContain('Druck &amp; Medien');
});

it('lets the template footer win over the generated one', function (): void {
    $html = (new Din5008Preset)->render(offer(), '', PageSetup::din5008(), [
        'footer_html' => '<p>Eigene Fußzeile</p>',
        'footer_columns' => [['Wird nicht gedruckt']],
    ]);

    expect($html)->toContain('Eigene Fußzeile')
        ->and($html)->not->toContain('Wird nicht gedruckt');
});

it('still falls back to the sender block without a template footer', function (): void {
    // Ein Dokument darf nie ohne Pflichtangaben gedruckt werden, nur weil
    // niemand eine Fußzeile gepflegt hat.
    $html = (new Din5008Preset)->render(offer(), '', PageSetup::din5008(), []);

    expect($html)->toContain('Peppermint Digital GmbH');
});

it('substitutes placeholders in the header and footer', function (): void {
    $html = builder()->html(
        offer(),
        '<p>Rumpf</p>',
        null,
        [
            'header_html' => '<p>{{ sender.name }}</p>',
            'footer_html' => '<p>Nummer {{ Angebotsnummer }}</p>',
        ],
    );

    expect($html)->toContain('Peppermint Digital GmbH')
        ->and($html)->toContain('Nummer AN-2026-0815')
        ->and($html)->not->toContain('{{');
});

it('honours the logo placement the template configured', function (): void {
    $html = (new Din5008Preset)->render(offer(), '', PageSetup::din5008(), [
        'logo' => 'data:image/png;base64,AAAA',
        'logo_align' => 'center',
        'logo_width' => 30,
        'logo_height' => 12,
    ]);

    expect($html)->toContain('text-align: center')
        ->and($html)->toContain('width: 30mm')
        ->and($html)->toContain('height: 12mm');
});
