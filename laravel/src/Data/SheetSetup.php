<?php

namespace Peppermint\DocumentBuilder\Data;

use InvalidArgumentException;

/**
 * A sheet of cards: the geometry a badge or ticket needs and a page does not.
 *
 * `PageSetup` describes a piece of paper. A card is rarely a piece of paper —
 * it is one of several on a sheet, at a fixed size, with gutters between and
 * possibly crop marks around. Without that notion every consumer builds the
 * grid by hand, and builds it twice: once for the single card and once for the
 * sheet. That is exactly the duplication this class exists to remove.
 *
 * All measurements are millimetres, like `PageSetup`.
 */
final class SheetSetup
{
    public function __construct(
        public readonly PageSetup $page,
        public readonly float $cardWidth,
        public readonly float $cardHeight,
        public readonly int $columns = 1,
        public readonly int $rows = 1,
        public readonly float $gutterX = 0.0,
        public readonly float $gutterY = 0.0,
        public readonly bool $cropMarks = false,
    ) {
        if ($cardWidth <= 0 || $cardHeight <= 0) {
            throw new InvalidArgumentException('A card needs a width and a height greater than zero.');
        }

        if ($columns < 1 || $rows < 1) {
            throw new InvalidArgumentException('A sheet needs at least one column and one row.');
        }

        if ($gutterX < 0 || $gutterY < 0) {
            throw new InvalidArgumentException('Gutters cannot be negative.');
        }

        // The guard that earns its keep. A grid one millimetre too wide does
        // not fail — DomPDF simply pushes the last column onto its own page,
        // and nobody notices until a stack of half-empty sheets comes out of
        // the printer on the morning of the event.
        $this->pruefePasst();
    }

    /**
     * One card, centred on its own page — the degenerate grid.
     *
     * Deliberately the same class rather than a separate path: `single` is
     * `1 × 1`, and treating it as its own concept is how the two-implementation
     * problem starts.
     */
    public static function single(float $cardWidth, float $cardHeight, ?PageSetup $page = null): self
    {
        return new self(
            page: $page ?? new PageSetup(marginTop: 20.0, marginRight: 20.0, marginBottom: 20.0, marginLeft: 20.0),
            cardWidth: $cardWidth,
            cardHeight: $cardHeight,
        );
    }

    /**
     * A grid, with the gutters worked out from whatever space is left over.
     *
     * Spreading the slack evenly beats asking a caller for gutters that must
     * happen to add up: the arithmetic is the same every time, and getting it
     * wrong is invisible until it is printed.
     */
    public static function grid(
        float $cardWidth,
        float $cardHeight,
        int $columns,
        int $rows,
        ?PageSetup $page = null,
        bool $cropMarks = false,
    ): self {
        $page ??= new PageSetup(marginTop: 15.0, marginRight: 15.0, marginBottom: 15.0, marginLeft: 15.0);

        $slackX = $page->paperWidth() - $page->marginLeft - $page->marginRight - ($cardWidth * $columns);
        $slackY = $page->paperHeight() - $page->marginTop - $page->marginBottom - ($cardHeight * $rows);

        return new self(
            page: $page,
            cardWidth: $cardWidth,
            cardHeight: $cardHeight,
            columns: $columns,
            rows: $rows,
            gutterX: $columns > 1 ? max(0.0, round($slackX / ($columns - 1), 2)) : 0.0,
            gutterY: $rows > 1 ? max(0.0, round($slackY / ($rows - 1), 2)) : 0.0,
            cropMarks: $cropMarks,
        );
    }

    /** How many cards fit on one sheet. */
    public function perPage(): int
    {
        return $this->columns * $this->rows;
    }

    /** How many sheets a given number of cards needs. */
    public function pages(int $cardCount): int
    {
        if ($cardCount < 1) {
            return 0;
        }

        return (int) ceil($cardCount / $this->perPage());
    }

    /**
     * Splits a list of cards into sheets, in order.
     *
     * The last sheet stays short rather than being padded — a half-empty sheet
     * is the honest outcome, and blank filler cards would be cut out and
     * thrown away by hand.
     *
     * @template T
     *
     * @param  list<T>  $cards
     * @return list<list<T>>
     */
    public function chunk(array $cards): array
    {
        if ($cards === []) {
            return [];
        }

        return array_values(array_map(
            array_values(...),
            array_chunk($cards, $this->perPage()),
        ));
    }

    /** Width the grid occupies, gutters included. */
    public function gridWidth(): float
    {
        return round($this->cardWidth * $this->columns + $this->gutterX * ($this->columns - 1), 2);
    }

    /** Height the grid occupies, gutters included. */
    public function gridHeight(): float
    {
        return round($this->cardHeight * $this->rows + $this->gutterY * ($this->rows - 1), 2);
    }

    /** Printable width of the page — paper minus its left and right margin. */
    public function printableWidth(): float
    {
        return round($this->page->paperWidth() - $this->page->marginLeft - $this->page->marginRight, 2);
    }

    /** Printable height of the page. */
    public function printableHeight(): float
    {
        return round($this->page->paperHeight() - $this->page->marginTop - $this->page->marginBottom, 2);
    }

    private function pruefePasst(): void
    {
        // Half a millimetre of tolerance: the margins come from doubles and a
        // grid computed by `grid()` may land a rounding step over the edge.
        $toleranz = 0.5;

        if ($this->gridWidth() > $this->printableWidth() + $toleranz) {
            throw new InvalidArgumentException(sprintf(
                'The grid is %.1fmm wide but only %.1fmm are printable. Reduce the columns, the card width or the page margins.',
                $this->gridWidth(),
                $this->printableWidth(),
            ));
        }

        if ($this->gridHeight() > $this->printableHeight() + $toleranz) {
            throw new InvalidArgumentException(sprintf(
                'The grid is %.1fmm tall but only %.1fmm are printable. Reduce the rows, the card height or the page margins.',
                $this->gridHeight(),
                $this->printableHeight(),
            ));
        }
    }
}
