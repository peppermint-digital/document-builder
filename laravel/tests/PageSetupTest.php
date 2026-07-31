<?php

use Peppermint\DocumentBuilder\Data\PageSetup;

it('converts offsets from the paper edge to the content box', function (): void {
    $page = PageSetup::din5008();

    // The DIN subject line sits 98.4mm below the paper edge. Absolutely
    // positioned CSS measures from the content box, which starts at the top
    // margin — getting this wrong is what prints the subject through the
    // address field.
    expect($page->fromPaperTop(98.4))->toBe(81.5)
        ->and($page->fromPaperTop(45.0))->toBe(28.1)
        ->and($page->fromPaperLeft(125.0))->toBe(100.9);
});

it('knows the paper dimensions and swaps them in landscape', function (): void {
    expect(PageSetup::din5008()->paperWidth())->toBe(210.0)
        ->and(PageSetup::din5008()->paperHeight())->toBe(297.0);

    $landscape = new PageSetup(paper: 'A4', orientation: 'landscape');

    expect($landscape->paperWidth())->toBe(297.0)
        ->and($landscape->paperHeight())->toBe(210.0);
});

it('falls back to A4 for an unknown paper size', function (): void {
    $page = new PageSetup(paper: 'PAPYRUS');

    expect($page->paperWidth())->toBe(210.0)
        ->and($page->paperHeight())->toBe(297.0);
});

it('builds from a config array', function (): void {
    $page = PageSetup::fromArray(['paper' => 'A5', 'margin_top' => 10]);

    expect($page->paper)->toBe('A5')
        ->and($page->marginTop)->toBe(10.0)
        // Untouched keys keep the DIN defaults.
        ->and($page->marginLeft)->toBe(24.1);
});
