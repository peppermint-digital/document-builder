<?php

use Peppermint\DocumentBuilder\Contracts\DocumentPayload;
use Peppermint\DocumentBuilder\Data\CardCode;
use Peppermint\DocumentBuilder\Data\CardData;

it('is a payload in its own right', function (): void {
    $karte = new CardData(type: 'badge', title: 'Anna Ahlers');

    expect($karte)->toBeInstanceOf(DocumentPayload::class)
        ->and($karte->type())->toBe('badge');
});

it('offers the supporting lines both by label and as a set', function (): void {
    $karte = new CardData(
        type: 'badge',
        title: 'Anna Ahlers',
        subtitle: 'Acme GmbH',
        rows: ['Rolle' => 'Referentin', 'Tisch' => '12'],
        code: new CardCode('tok_abc123'),
    );

    $p = $karte->placeholders();

    // By label, so a layout can single one out …
    expect($p['row.Rolle'])->toBe('Referentin')
        ->and($p['row.Tisch'])->toBe('12')
        // … and the headline pieces under stable names.
        ->and($p['title'])->toBe('Anna Ahlers')
        ->and($p['subtitle'])->toBe('Acme GmbH')
        ->and($p['code'])->toBe('tok_abc123');
});

it('does not print a gap where an empty line would have been', function (): void {
    $karte = new CardData(
        type: 'badge',
        title: 'Anna Ahlers',
        rows: ['Firma' => '', 'Rolle' => 'Referentin', 'Tisch' => null],
    );

    expect($karte->gefuellteZeilen())->toBe(['Rolle' => 'Referentin']);
});

it('has no code at all when none was given', function (): void {
    expect((new CardData(type: 'badge', title: 'Anna'))->placeholders()['code'])->toBeNull();
});

it('builds itself from an array the way the business shape does', function (): void {
    $karte = CardData::fromArray([
        'type' => 'ticket',
        'title' => 'DevCon 2026',
        'subtitle' => 'Tagesticket',
        'rows' => ['Einlass' => '09:00'],
        'code' => ['value' => 'tok_xyz', 'kind' => CardCode::CODE128, 'size' => 12],
        'custom' => ['hinweis' => 'Bitte Ausweis mitbringen'],
    ]);

    expect($karte->type())->toBe('ticket')
        ->and($karte->code?->kind)->toBe(CardCode::CODE128)
        ->and($karte->code?->size)->toBe(12.0)
        ->and($karte->placeholders()['hinweis'])->toBe('Bitte Ausweis mitbringen');
});

it('refuses a code that would print an empty box', function (): void {
    expect(fn () => new CardCode('   '))->toThrow(InvalidArgumentException::class);
});

it('refuses a code kind no renderer knows', function (): void {
    expect(fn () => new CardCode('tok_abc', kind: 'aztec'))
        ->toThrow(InvalidArgumentException::class, 'aztec');
});
