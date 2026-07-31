<?php

use Peppermint\DocumentBuilder\Data\PageSetup;
use Peppermint\DocumentBuilder\Presets\Din5008Preset;

it('places the address field and subject line at the DIN offsets', function (): void {
    $css = (new Din5008Preset)->css(PageSetup::din5008());

    // 45mm and 98.4mm from the paper edge, converted to the content box.
    expect($css)->toContain('top: 28.1mm')
        ->and($css)->toContain('margin-top: 81.5mm')
        ->and($css)->toContain('width: 85mm');
});

it('emits flat CSS only', function (): void {
    $css = (new Din5008Preset)->css(PageSetup::din5008());

    // DomPDF's default media type is screen, and it supports neither of these.
    expect($css)->not->toContain('@media')
        ->and($css)->not->toContain('display: flex')
        ->and($css)->not->toContain('display: grid');
});

it('keeps the totals block from being torn across a page break', function (): void {
    $css = (new Din5008Preset)->css(PageSetup::din5008());

    expect($css)->toContain('page-break-inside: avoid');
});

it('renders the recipient into the address field and the meta into the info block', function (): void {
    $html = (new Din5008Preset)->render(offer(), '<p>body</p>', PageSetup::din5008());

    expect($html)->toContain('Mustermann &amp; Sohn GmbH')
        ->and($html)->toContain('class="db-address-supplement">Einschreiben')
        ->and($html)->toContain('Angebotsnummer')
        ->and($html)->toContain('AN-2026-0815');
});

it('draws page numbers through a page script', function (): void {
    $html = (new Din5008Preset)->render(offer(), '', PageSetup::din5008());

    expect($html)->toContain('page_script')
        ->and($html)->toContain('$PAGE_COUNT');
});

it('omits page numbers and fold marks when they are switched off', function (): void {
    $html = (new Din5008Preset)->render(offer(), '', PageSetup::din5008(), [
        'page_numbers' => false,
        'fold_marks' => false,
    ]);

    // The stylesheet always carries the rules; what must disappear is the
    // element itself.
    expect($html)->not->toContain('page_script')
        ->and($html)->not->toContain('<div class="db-mark db-mark-fold-1">');
});

it('falls back to the sender block so a document is never printed without its details', function (): void {
    $html = (new Din5008Preset)->render(offer(), '', PageSetup::din5008());

    expect($html)->toContain('class="db-footer"')
        ->and($html)->toContain('Peppermint Digital GmbH');
});
