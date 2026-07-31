import type { LineItemColumn, PageSetup } from './types';

/**
 * The columns a German business document shows by default.
 *
 * Mirrors `LineItemsRenderer::DEFAULT_COLUMNS`. Widths add up to 100% — a
 * colgroup that overshoots makes DomPDF fall back to automatic layout, which is
 * exactly what these widths exist to prevent.
 */
export const DEFAULT_COLUMNS: LineItemColumn[] = [
    { key: 'position', label: 'Pos.', width: '7%', align: 'right' },
    { key: 'description', label: 'Bezeichnung', width: '45%' },
    { key: 'quantity', label: 'Menge', width: '10%', align: 'right', format: 'decimal' },
    { key: 'unit', label: 'Einheit', width: '10%' },
    { key: 'unit_price', label: 'Einzelpreis', width: '14%', align: 'right', format: 'currency' },
    { key: 'total', label: 'Gesamt', width: '14%', align: 'right', format: 'currency' },
];

/** DIN 5008 form B — the default page geometry. */
export const DIN_5008: PageSetup = {
    paper: 'A4',
    orientation: 'portrait',
    marginTop: 16.9,
    marginRight: 20,
    marginBottom: 30,
    marginLeft: 24.1,
};

/** Portrait dimensions in millimetres, mirroring `PageSetup::dimensions()`. */
const PAPER: Record<string, [number, number]> = {
    A3: [297, 420],
    A4: [210, 297],
    A5: [148, 210],
    LETTER: [215.9, 279.4],
    LEGAL: [215.9, 355.6],
};

/** Width and height of a page in millimetres, honouring the orientation. */
export function paperSize(page: PageSetup): { width: number; height: number } {
    const [width, height] = PAPER[page.paper] ?? PAPER.A4!;

    return page.orientation === 'landscape'
        ? { width: height, height: width }
        : { width, height };
}

/**
 * Converts an offset measured from the paper edge into one measured from the
 * content box — the same conversion `PageSetup::fromPaperTop()` does in PHP.
 * The canvas has to agree with the renderer or the preview lies.
 */
export function fromPaperTop(page: PageSetup, millimetres: number): number {
    return Math.round((millimetres - page.marginTop) * 100) / 100;
}

export function fromPaperLeft(page: PageSetup, millimetres: number): number {
    return Math.round((millimetres - page.marginLeft) * 100) / 100;
}
