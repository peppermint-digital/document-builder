<?php

use Peppermint\DocumentBuilder\Data\PageSetup;
use Peppermint\DocumentBuilder\Data\SheetSetup;

/**
 * Geometry, not looks. Whether a badge is pretty is a template question;
 * whether it lands on the paper is this one.
 */
it('treats a single card as a one by one grid', function (): void {
    $sheet = SheetSetup::single(76, 124);

    expect($sheet->perPage())->toBe(1)
        ->and($sheet->columns)->toBe(1)
        ->and($sheet->rows)->toBe(1)
        ->and($sheet->gutterX)->toBe(0.0);
});

it('counts how many sheets a stack of cards needs', function (): void {
    $sheet = SheetSetup::grid(61, 54, columns: 2, rows: 4);

    expect($sheet->perPage())->toBe(8)
        ->and($sheet->pages(0))->toBe(0)
        ->and($sheet->pages(1))->toBe(1)
        ->and($sheet->pages(8))->toBe(1)
        ->and($sheet->pages(9))->toBe(2)
        ->and($sheet->pages(17))->toBe(3);
});

it('leaves the last sheet short instead of padding it', function (): void {
    $sheet = SheetSetup::grid(61, 54, columns: 2, rows: 2);
    $seiten = $sheet->chunk(['a', 'b', 'c', 'd', 'e']);

    // Blank filler cards would be cut out and thrown away by hand.
    expect($seiten)->toHaveCount(2)
        ->and($seiten[0])->toBe(['a', 'b', 'c', 'd'])
        ->and($seiten[1])->toBe(['e']);
});

it('has nothing to chunk when there are no cards', function (): void {
    expect(SheetSetup::grid(61, 54, columns: 2, rows: 2)->chunk([]))->toBe([]);
});

it('spreads the leftover space evenly between the columns', function (): void {
    $page = new PageSetup(marginTop: 15, marginRight: 15, marginBottom: 15, marginLeft: 15);
    $sheet = SheetSetup::grid(61, 54, columns: 2, rows: 4, page: $page);

    // A4 is 210mm wide, 30mm goes to the margins, two 61mm cards take 122mm.
    // What is left is a single gutter of 58mm.
    expect($sheet->printableWidth())->toBe(180.0)
        ->and($sheet->gutterX)->toBe(58.0);
});

it('refuses a grid that is wider than the paper', function (): void {
    // Four 61mm cards need 244mm; A4 offers 180mm between 15mm margins. Without
    // this guard DomPDF quietly pushes the last column onto its own page, and
    // it shows up as a stack of half-empty sheets on the morning of the event.
    expect(fn () => new SheetSetup(
        page: new PageSetup(marginTop: 15, marginRight: 15, marginBottom: 15, marginLeft: 15),
        cardWidth: 61,
        cardHeight: 54,
        columns: 4,
        rows: 1,
    ))->toThrow(InvalidArgumentException::class, 'printable');
});

it('refuses a grid that is taller than the paper', function (): void {
    expect(fn () => new SheetSetup(
        page: new PageSetup(marginTop: 15, marginRight: 15, marginBottom: 15, marginLeft: 15),
        cardWidth: 61,
        cardHeight: 124,
        columns: 1,
        rows: 3,
    ))->toThrow(InvalidArgumentException::class, 'printable');
});

it('refuses a card without a size', function (): void {
    expect(fn () => SheetSetup::single(0, 124))->toThrow(InvalidArgumentException::class);
});

it('refuses a sheet without a column', function (): void {
    expect(fn () => new SheetSetup(page: PageSetup::din5008(), cardWidth: 61, cardHeight: 54, columns: 0))
        ->toThrow(InvalidArgumentException::class);
});

it('measures the grid including its gutters', function (): void {
    $sheet = new SheetSetup(
        page: new PageSetup(marginTop: 15, marginRight: 15, marginBottom: 15, marginLeft: 15),
        cardWidth: 61,
        cardHeight: 54,
        columns: 2,
        rows: 2,
        gutterX: 8,
        gutterY: 6,
    );

    expect($sheet->gridWidth())->toBe(130.0)   // 61 + 8 + 61
        ->and($sheet->gridHeight())->toBe(114.0); // 54 + 6 + 54
});

it('carries the real badge geometries from Peppermint Connect', function (): void {
    // The two sizes that exist today: a hanging badge printed one per page,
    // and small labels printed two across. Both must be expressible.
    $umhaenger = SheetSetup::single(76, 124);
    $aufkleber = SheetSetup::grid(61, 54, columns: 2, rows: 4);

    expect($umhaenger->perPage())->toBe(1)
        ->and($aufkleber->perPage())->toBe(8);
});
