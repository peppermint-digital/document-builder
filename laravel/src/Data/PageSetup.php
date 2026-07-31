<?php

namespace Peppermint\DocumentBuilder\Data;

/**
 * Physical page geometry, in millimetres.
 *
 * Every renderer driver receives this instead of engine specific options, so
 * swapping DomPDF for a headless browser does not change any caller.
 */
class PageSetup
{
    /**
     * @param  'portrait'|'landscape'  $orientation
     */
    public function __construct(
        public readonly string $paper = 'A4',
        public readonly string $orientation = 'portrait',
        public readonly float $marginTop = 16.9,
        public readonly float $marginRight = 20.0,
        public readonly float $marginBottom = 30.0,
        public readonly float $marginLeft = 24.1,
    ) {}

    /**
     * DIN 5008 form B geometry — the default for German business letters.
     */
    public static function din5008(): self
    {
        return new self;
    }

    /**
     * @param  array{paper?: string, orientation?: string, margin_top?: float|int, margin_right?: float|int, margin_bottom?: float|int, margin_left?: float|int}  $attributes
     */
    public static function fromArray(array $attributes): self
    {
        $defaults = new self;

        return new self(
            paper: $attributes['paper'] ?? $defaults->paper,
            orientation: $attributes['orientation'] ?? $defaults->orientation,
            marginTop: (float) ($attributes['margin_top'] ?? $defaults->marginTop),
            marginRight: (float) ($attributes['margin_right'] ?? $defaults->marginRight),
            marginBottom: (float) ($attributes['margin_bottom'] ?? $defaults->marginBottom),
            marginLeft: (float) ($attributes['margin_left'] ?? $defaults->marginLeft),
        );
    }

    /**
     * Height of the paper in millimetres, honouring the orientation. Needed to
     * convert DIN offsets, which are measured from the paper edge, into CSS
     * offsets, which are measured from the content box.
     */
    public function paperHeight(): float
    {
        [, $height] = $this->dimensions();

        return $height;
    }

    /**
     * Width of the paper in millimetres, honouring the orientation.
     */
    public function paperWidth(): float
    {
        [$width] = $this->dimensions();

        return $width;
    }

    /**
     * Converts an offset measured from the paper edge into one measured from
     * the content box, which is what absolutely positioned CSS actually uses.
     *
     * This single conversion is the reason the skeleton is package owned:
     * getting it wrong prints the subject line on top of the address field.
     */
    public function fromPaperTop(float $millimetres): float
    {
        return round($millimetres - $this->marginTop, 2);
    }

    /**
     * Same conversion for the horizontal axis.
     */
    public function fromPaperLeft(float $millimetres): float
    {
        return round($millimetres - $this->marginLeft, 2);
    }

    /**
     * Portrait dimensions of the known paper sizes, swapped when the page is
     * laid out in landscape.
     *
     * @return array{0: float, 1: float} width and height in millimetres
     */
    private function dimensions(): array
    {
        [$width, $height] = match (strtoupper($this->paper)) {
            'A5' => [148.0, 210.0],
            'A3' => [297.0, 420.0],
            'LETTER' => [215.9, 279.4],
            'LEGAL' => [215.9, 355.6],
            default => [210.0, 297.0],
        };

        return $this->orientation === 'landscape' ? [$height, $width] : [$width, $height];
    }
}
