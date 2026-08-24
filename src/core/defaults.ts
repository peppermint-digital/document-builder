import type { CardSetup, LineItemColumn, PageSetup } from './types';

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

/**
 * Das Kartenmass, mit dem eine Vorlage beginnt, wenn der Aufrufer schweigt.
 *
 * 76 x 124 mm ist das Umhaengeschild — das haeufigste der drei Formate, die es
 * in Peppermint Connect gibt, und dasjenige, an dem die Schriftableitung
 * geeicht wurde.
 */
export const CARD_DEFAULT: CardSetup = {
    cardWidth: 76,
    cardHeight: 124,
    columns: 1,
    rows: 1,
    gutterX: 0,
    gutterY: 0,
    cropMarks: false,
    paddingEm: 0,
    borderMm: 0,
    fontPt: null,
};

/**
 * Rundet wie PHPs `round()` — kaufmaennisch, auf so viele Nachkommastellen.
 *
 * Existiert, damit die Leinwand auf dieselbe Zahl kommt wie der Renderer. Wer
 * hier `toFixed` nimmt, bekommt eine Zeichenkette und irgendwann einen
 * Millimeter Abweichung, den niemand mehr zuordnet.
 */
export function round(value: number, digits = 2): number {
    const faktor = 10 ** digits;

    return Math.round((value + Number.EPSILON) * faktor) / faktor;
}

/** Faktor der Schriftableitung — spiegelt `CardPreset::SCHRIFT_FAKTOR`. */
const SCHRIFT_FAKTOR = 1.15;

/** Titelgroesse in Vielfachen der Basis — spiegelt `CardPreset::TITEL_EM`. */
export const CARD_TITLE_EM = 2.2;

/**
 * Basisschriftgroesse einer Karte in Punkt.
 *
 * Aus der Wurzel der Kartenhoehe, nicht proportional: die beiden von Hand
 * gesetzten Namensschilder (124mm/28pt und 54mm/18pt) lassen sich linear nicht
 * gemeinsam treffen, weil das kleine Schild bewusst verhaeltnismaessig groesser
 * setzt. Genau diese Ableitung ist der Grund, warum EINE Vorlage alle Formate
 * bedient. Spiegelt `CardPreset::basisSchriftgroesse()`.
 */
export function cardBaseFontPt(card: CardSetup): number {
    if (card.fontPt !== null && card.fontPt > 0) {
        return round(card.fontPt, 1);
    }

    return round(Math.sqrt(card.cardHeight) * SCHRIFT_FAKTOR, 1);
}

/** Innenabstand in Millimetern — spiegelt `CardPreset::polster()`. 1pt = 0.352778mm. */
export function cardPaddingMm(card: CardSetup): number {
    return round(card.paddingEm * cardBaseFontPt(card) * 0.352778, 2);
}

/**
 * Die Flaeche, die dem Inhalt bleibt.
 *
 * Hier ausgerechnet und nicht `box-sizing` ueberlassen, weil DomPDF
 * `box-sizing: border-box` nicht umsetzt: Innenabstand und Rahmen kaemen dort
 * oben drauf, statt eingerechnet zu werden, und die Karte wuerde breiter als
 * ihr Platz im Raster. Die Leinwand muss denselben Fehler machen wie das
 * Papier — sonst zeigt sie eine Karte, die es nicht gibt. Spiegelt die
 * Rechnung in `CardPreset::css()`.
 */
export function cardInnerSize(card: CardSetup): { width: number; height: number } {
    const abzug = cardPaddingMm(card) + round(card.borderMm, 2);

    return {
        width: round(card.cardWidth - 2 * abzug, 2),
        height: round(card.cardHeight - 2 * abzug, 2),
    };
}

/** Platz, den das Raster einnimmt — Rinnen eingerechnet. Spiegelt `SheetSetup::gridWidth()`. */
export function cardGridSize(card: CardSetup): { width: number; height: number } {
    return {
        width: round(card.cardWidth * card.columns + card.gutterX * (card.columns - 1), 2),
        height: round(card.cardHeight * card.rows + card.gutterY * (card.rows - 1), 2),
    };
}

/** Wo eine Karte im Raster sitzt. Spiegelt `CardPreset::platziert()`. */
export function cardPosition(card: CardSetup, index: number): { left: number; top: number } {
    const spalte = index % card.columns;
    const zeile = Math.floor(index / card.columns);

    return {
        left: round(spalte * (card.cardWidth + card.gutterX), 2),
        top: round(zeile * (card.cardHeight + card.gutterY), 2),
    };
}
