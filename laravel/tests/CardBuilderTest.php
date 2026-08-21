<?php

use Peppermint\DocumentBuilder\CardBuilder;
use Peppermint\DocumentBuilder\Contracts\CodeRenderer;
use Peppermint\DocumentBuilder\Contracts\DocumentRenderer;
use Peppermint\DocumentBuilder\Data\CardCode;
use Peppermint\DocumentBuilder\Data\CardData;
use Peppermint\DocumentBuilder\Data\PageSetup;
use Peppermint\DocumentBuilder\Data\SheetSetup;
use Peppermint\DocumentBuilder\Renderers\BundledCodeRenderer;
use Peppermint\DocumentBuilder\Services\PlaceholderRenderer;

function kartenBauer(?CodeRenderer $codes = null): CardBuilder
{
    $renderer = new class implements DocumentRenderer
    {
        public function render(string $html, PageSetup $page): string
        {
            return $html;
        }

        public function isAvailable(): bool
        {
            return true;
        }
    };

    return new CardBuilder($renderer, new PlaceholderRenderer, $codes);
}

function anna(?CardCode $code = null): CardData
{
    return new CardData(
        type: 'badge',
        title: 'Anna Ahlers',
        subtitle: 'Acme GmbH',
        rows: ['Rolle' => 'Referentin'],
        code: $code,
    );
}

it('substitutes each card separately', function (): void {
    $sheet = SheetSetup::grid(61, 54, columns: 2, rows: 2);
    $body = '<p class="db-card-title">{{ title }}</p><p>{{ subtitle }}</p>';

    $html = kartenBauer()->html([
        anna(),
        new CardData(type: 'badge', title: 'Bob Beck', subtitle: 'Beta AG'),
    ], $body, $sheet);

    expect($html)->toContain('Anna Ahlers')
        ->and($html)->toContain('Acme GmbH')
        ->and($html)->toContain('Bob Beck')
        ->and($html)->toContain('Beta AG');
});

it('reaches a supporting line by its label', function (): void {
    $sheet = SheetSetup::single(76, 124);
    $html = kartenBauer()->html([anna()], '<p>{{ row.Rolle }}</p>', $sheet);

    expect($html)->toContain('Referentin');
});

it('prints the code as an embedded image', function (): void {
    $sheet = SheetSetup::single(76, 124);
    $html = kartenBauer(new BundledCodeRenderer)
        ->html([anna(new CardCode('tok_abc123', size: 25))], '<div>{{ code_image }}</div>', $sheet);

    // A data URI and not a path: the renderer has neither a filesystem nor a
    // web server in front of it when a queue worker prints a sheet.
    expect($html)->toContain('src="data:image/png;base64,')
        ->and($html)->toContain('width: 25mm');
});

it('prints nothing rather than an empty box when there is no code', function (): void {
    $sheet = SheetSetup::single(76, 124);
    $html = kartenBauer(new BundledCodeRenderer)->html([anna()], '<div>[{{ code_image }}]</div>', $sheet);

    // An empty square looks like a code that failed to scan, and someone would
    // stand at the door trying to scan it.
    expect($html)->toContain('[]')
        ->and($html)->not->toContain('<img');
});

it('prints nothing when no code driver is available', function (): void {
    $sheet = SheetSetup::single(76, 124);
    $html = kartenBauer(null)->html([anna(new CardCode('tok_abc'))], '<div>[{{ code_image }}]</div>', $sheet);

    expect($html)->toContain('[]');
});

it('expands the template token exactly once, never the card\'s own text', function (): void {
    $sheet = SheetSetup::single(76, 124);

    // The card's own title happens to contain the token. The template asks for
    // the code once, legitimately.
    $karte = new CardData(type: 'badge', title: '{{ code_image }}', code: new CardCode('tok_abc'));
    $html = kartenBauer(new BundledCodeRenderer)->html([$karte], '<p>{{ title }}</p>{{ code_image }}', $sheet);

    // EXACTLY one, and both halves of that matter:
    //
    // - Two would mean the card's own text grew a code — what parking the
    //   token behind a sentinel before substitution prevents.
    // - Zero would mean the legitimate one stopped working, which an
    //   assertion on „not two" alone would happily accept.
    expect(substr_count($html, '<img'))->toBe(1);
});

it('gives a barcode its height and lets the width follow', function (): void {
    $sheet = SheetSetup::single(76, 124);
    $html = kartenBauer(new BundledCodeRenderer)
        ->html([anna(new CardCode('123456', kind: CardCode::CODE128, size: 15))], '<div>{{ code_image }}</div>', $sheet);

    // Squeezing a linear code into a square destroys the bar widths.
    expect($html)->toContain('height: 15mm')
        ->and($html)->not->toContain('width: 15mm');
});

it('names the placeholders a template wants but the card has not', function (): void {
    $fehlend = kartenBauer()->missingPlaceholders(anna(), '<p>{{ title }} {{ tischnummer }} {{ code_image }}</p>');

    // `code_image` is markup, not a value — it must not be reported missing.
    expect($fehlend)->toBe(['tischnummer']);
});

it('spreads a stack of cards across as many sheets as it needs', function (): void {
    $sheet = SheetSetup::grid(61, 54, columns: 2, rows: 2);
    $karten = array_map(
        fn (int $i): CardData => new CardData(type: 'badge', title: "Person {$i}"),
        range(1, 7),
    );

    $html = kartenBauer()->html($karten, '<p>{{ title }}</p>', $sheet);

    expect(substr_count($html, 'class="db-sheet"'))->toBe(2)
        ->and(substr_count($html, 'class="db-card"'))->toBe(7)
        ->and($html)->toContain('Person 7');
});
