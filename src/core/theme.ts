import {
    cardBaseFontPt,
    cardInnerSize,
    cardPaddingMm,
    CARD_TITLE_EM,
    fromPaperLeft,
    fromPaperTop,
    paperSize,
    round,
} from './defaults';
import type { CardSetup, PageSetup, SkeletonPreview } from './types';

/** Brand palette offered in the colour picker. */
export const BRAND_COLORS = ['#1a1a1a', '#666666', '#0f766e', '#b91c1c', '#1d4ed8', '#ffffff'];

/**
 * Font stacks that survive the print pipeline. DomPDF only knows the fonts it
 * has files for; offering a designer-favourite that silently falls back to
 * Helvetica is worse than not offering it.
 */
export const FONT_STACKS = [
    { value: '"DejaVu Sans", sans-serif', name: 'DejaVu Sans' },
    { value: 'Helvetica, Arial, sans-serif', name: 'Helvetica' },
    { value: '"DejaVu Serif", Georgia, serif', name: 'DejaVu Serif' },
    { value: '"DejaVu Sans Mono", monospace', name: 'Monospace' },
];

/**
 * CSS injected into the canvas so the editor looks like the page it prints to.
 *
 * The body is sized to the real paper with the real margins, which means a
 * block that looks like it fits on the page does fit on the page. The zones the
 * preset owns — address field, fold marks, footer — are drawn as locked
 * decoration: visible for orientation, not selectable, not movable.
 */
export function canvasCss(page: PageSetup, preview: SkeletonPreview = {}): string {
    const { width } = paperSize(page);
    const contentWidth = width - page.marginLeft - page.marginRight;

    const addressTop = fromPaperTop(page, 45);
    const addressLeft = fromPaperLeft(page, 20);
    const infoLeft = fromPaperLeft(page, 125);
    const subjectTop = fromPaperTop(page, 98.4);
    const foldOne = fromPaperTop(page, 87);
    const foldTwo = fromPaperTop(page, 192);
    const hole = fromPaperTop(page, 148.5);
    const markLeft = fromPaperLeft(page, 3);

    // The free zone starts below the subject line. Everything above belongs to
    // the preset, so the body gets pushed down by exactly that much.
    const bodyOffset = subjectTop + 8;

    const addressLabel = preview.recipientLines?.length
        ? ''
        : "content: 'Anschriftfeld — vom Dokument gefüllt';";

    return `
        html { background: #f1f5f9; }

        body {
            position: relative;
            box-sizing: border-box;
            width: ${width}mm;
            min-height: ${paperSize(page).height}mm;
            /* !important, weil GrapesJS nach diesem Stylesheet ein eigenes
               body { margin: 0 } einspielt und das Blatt sonst links klebt. */
            margin: 8mm auto !important;
            padding: ${page.marginTop}mm ${page.marginRight}mm ${page.marginBottom}mm ${page.marginLeft}mm;
            background: #ffffff;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.18);
            font-family: "DejaVu Sans", Helvetica, Arial, sans-serif;
            font-size: 10pt;
            line-height: 1.35;
            color: #1a1a1a;
        }

        /* Address window — locked decoration. */
        body::before {
            position: absolute;
            top: ${page.marginTop + addressTop}mm;
            left: ${page.marginLeft + addressLeft}mm;
            width: 85mm;
            height: 45mm;
            box-sizing: border-box;
            border: 0.3mm dashed #cbd5e1;
            padding: 2mm;
            font-size: 7pt;
            color: #94a3b8;
            ${addressLabel}
            pointer-events: none;
        }

        /* Information block and subject rule, drawn from the same offsets the
           preset uses so the canvas cannot drift from the PDF. */
        body::after {
            position: absolute;
            top: ${page.marginTop + addressTop}mm;
            left: ${page.marginLeft + infoLeft}mm;
            width: 75mm;
            height: 30mm;
            box-sizing: border-box;
            border: 0.3mm dashed #e2e8f0;
            padding: 2mm;
            font-size: 7pt;
            color: #94a3b8;
            content: 'Informationsblock';
            pointer-events: none;
        }

        .db-canvas-subject {
            position: absolute;
            top: ${page.marginTop + subjectTop}mm;
            left: ${page.marginLeft}mm;
            width: ${contentWidth}mm;
            font-weight: bold;
            font-size: 11pt;
            color: #94a3b8;
            pointer-events: none;
        }

        .db-canvas-mark {
            position: absolute;
            left: ${page.marginLeft + markLeft}mm;
            width: 5mm;
            border-top: 0.3mm solid #cbd5e1;
            pointer-events: none;
        }
        .db-canvas-fold-1 { top: ${page.marginTop + foldOne}mm; }
        .db-canvas-fold-2 { top: ${page.marginTop + foldTwo}mm; }
        .db-canvas-hole { top: ${page.marginTop + hole}mm; width: 8mm; }

        /* Die drei Zonen. Position und Höhe gehören dem Gerüst — hier wird nur
           sichtbar gemacht, wo sie liegen. */
        .db-zone { position: relative; }

        .db-zone-header {
            position: absolute;
            top: ${page.marginTop}mm;
            left: ${page.marginLeft}mm;
            right: ${page.marginRight}mm;
            min-height: 12mm;
            overflow: hidden;
        }

        .db-zone-body { margin-top: ${bodyOffset}mm; }

        .db-zone-footer {
            position: absolute;
            left: ${page.marginLeft}mm;
            right: ${page.marginRight}mm;
            bottom: 8mm;
            min-height: 12mm;
            border-top: 0.3mm dashed #cbd5e1;
            padding-top: 1.5mm;
            font-size: 7pt;
            color: #475569;
        }

        /* Leere Zonen sind sonst unsichtbar und niemand fände sie zum Befüllen. */
        .db-zone-header:empty::after { content: 'Briefkopf — hier Bausteine ablegen'; }
        .db-zone-footer:empty::after { content: 'Fußzeile — hier Bausteine ablegen'; }
        .db-zone-header:empty::after,
        .db-zone-footer:empty::after {
            display: block;
            font-size: 7pt;
            color: #94a3b8;
            font-style: italic;
        }

        /* Blocks the builder places. Deliberately close to the print CSS. */
        p { margin: 0 0 3mm 0; }
        .db-spacer { display: block; }
        .db-divider { border: 0; border-top: 0.2mm solid #cccccc; margin: 3mm 0; }
        .db-page-break {
            border-top: 0.4mm dashed #94a3b8;
            margin: 4mm 0;
            text-align: center;
            font-size: 7pt;
            color: #94a3b8;
        }
        .db-columns { width: 100%; border-collapse: collapse; }
        .db-columns > tbody > tr > td { vertical-align: top; padding: 0 2mm; }

        table.db-line-items { width: 100%; border-collapse: collapse; margin-top: 6mm; }
        table.db-line-items thead th {
            border-bottom: 0.4mm solid #1a1a1a;
            padding: 1.5mm 1mm;
            text-align: left;
            font-size: 9pt;
        }
        table.db-line-items tbody td {
            border-bottom: 0.1mm solid #dddddd;
            padding: 1.5mm 1mm;
            vertical-align: top;
        }
        table.db-line-items .db-note { display: block; color: #666666; font-size: 8pt; }
        .db-align-right { text-align: right; }
        .db-align-center { text-align: center; }

        table.db-totals { margin-top: 6mm; margin-left: auto; width: 70mm; border-collapse: collapse; }
        table.db-totals td { padding: 1mm 0; }
        table.db-totals .db-total-amount { text-align: right; }
        table.db-totals .db-total-gross td { border-top: 0.4mm solid #1a1a1a; font-weight: bold; }

        /* Sample data is greyed so nobody mistakes it for their content. */
        [data-db-sample] { color: #64748b; }
    `;
}

/**
 * The locked decoration itself. Added to the canvas once and excluded from the
 * export — the preset renders these for real at print time.
 */
export function skeletonHtml(preview: SkeletonPreview = {}): string {
    const subject = preview.subject ?? 'Betreff — vom Dokument gefüllt';

    return [
        '<div class="db-canvas-mark db-canvas-fold-1" data-db-skeleton="1"></div>',
        '<div class="db-canvas-mark db-canvas-fold-2" data-db-skeleton="1"></div>',
        '<div class="db-canvas-mark db-canvas-hole" data-db-skeleton="1"></div>',
        `<div class="db-canvas-subject" data-db-skeleton="1">${escapeHtml(subject)}</div>`,
    ].join('');
}

export function escapeHtml(value: string): string {
    return value
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

/**
 * Leinwand-CSS fuer eine Karte.
 *
 * Bearbeitet wird EINE Karte, nicht der Bogen: der Bogen ist Sache des Presets
 * beim Druck, und eine Leinwand mit zwoelf gleichen Karten waere zwoelfmal
 * dieselbe Bearbeitung. Das Blatt selbst ist deshalb die Karte — in ihrer
 * Innenflaeche, mit ihrem Innenabstand und ihrem Rahmen, so wie DomPDF sie
 * setzt.
 *
 * Die Klassennamen sind dieselben wie in `CardPreset::css()`. Das ist der
 * eigentliche Punkt: was hier aussieht wie im Editor, sieht auf dem Papier
 * genauso aus, weil beide Seiten dieselben Regeln auf dieselben Klassen legen.
 */
export function cardCanvasCss(card: CardSetup, preview: SkeletonPreview = {}): string {
    const innen = cardInnerSize(card);
    const polster = cardPaddingMm(card);
    const rahmen = round(card.borderMm, 2);
    const basis = cardBaseFontPt(card);
    const rahmenfarbe = rahmen > 0 ? '#cbd5e1' : 'transparent';

    // Ohne Code waere die Karte am Einlass wertlos. Er gehoert dem Geruest,
    // wie die Pflichtangaben im DIN-Preset — hier nur der Platz dafuer.
    //
    // Die Beschriftung MUSS an ein Pseudo-Element: `content` auf dem `div`
    // selbst rendert nichts, und der Platzhalter stuende als leerer,
    // unerklaerter Kasten auf der Karte. Vom Pruefstand gefunden, nicht vom
    // Test — ein leerer Kasten ist syntaktisch tadellos.

    return `
        html { background: #f1f5f9; }

        body {
            position: relative;
            /* Innenmass, NICHT box-sizing: DomPDF setzt border-box nicht um.
               Die Leinwand muss hier denselben Weg gehen wie das Papier. */
            width: ${innen.width}mm;
            min-height: ${innen.height}mm;
            padding: ${polster}mm;
            border: ${rahmen}mm solid ${rahmenfarbe};
            margin: 8mm auto !important;
            background: #ffffff;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.18);
            font-family: "DejaVu Sans", Helvetica, Arial, sans-serif;
            font-size: ${basis}pt;
            line-height: 1.35;
            color: #1a1a1a;
            overflow: hidden;
        }

        /* Dieselben Regeln wie in CardPreset::css() — sonst luegt die Vorschau. */
        .db-card-title { font-size: ${CARD_TITLE_EM}em; font-weight: bold; line-height: 1.15; }
        .db-card-subtitle { font-size: 1.15em; }
        .db-card-rows { font-size: 0.9em; }
        .db-card-code { text-align: center; }

        /* Die Zonen fliessen auf der Karte, statt am Rand zu kleben. Am
           Kartenboden verankert ueberlagert die Fusszeile die letzte Zeile —
           probiert, gesehen, verworfen (siehe #4432). */
        .db-zone { position: relative; }
        .db-zone-body { margin-top: 0; }

        .db-zone-footer {
            margin-top: 1.5mm;
            border-top: 0.3mm dashed #cbd5e1;
            padding-top: 1mm;
            font-size: 0.75em;
            color: #475569;
        }

        .db-zone-header:empty::after { content: 'Kartenkopf — hier Bausteine ablegen'; }
        .db-zone-footer:empty::after { content: 'Kartenfuß — hier Bausteine ablegen'; }
        .db-zone-header:empty::after,
        .db-zone-footer:empty::after {
            display: block;
            font-size: 0.7em;
            color: #94a3b8;
            font-style: italic;
        }

        .db-canvas-code {
            display: block;
            margin: 2mm auto 0 auto;
            /* 18mm, nicht 25: mit Ueberschrift und Trennlinie passt der
               vollste Entwurf sonst nicht mehr auf 124mm (#4432). */
            width: 18mm;
            height: 18mm;
            box-sizing: border-box;
            border: 0.3mm dashed #cbd5e1;
            pointer-events: none;
        }

        .db-canvas-code::after {
            content: 'Code — vom Dokument gefüllt';
            display: block;
            padding: 1mm;
            font-size: 6pt;
            line-height: 1.2;
            color: #94a3b8;
            text-align: center;
        }

        p { margin: 0 0 1.5mm 0; }
        .db-divider { border: 0; border-top: 0.2mm solid #cccccc; margin: 1.5mm 0; }
        [data-db-sample] { color: #64748b; }
    `;
}

/**
 * Die unantastbare Dekoration der Karte.
 *
 * Bislang nur der Code-Platz. Er ist bewusst keine Komponente: als Baustein
 * waere er loeschbar, und ein Schild ohne Code ist am Einlass wertlos.
 */
export function cardSkeletonHtml(_preview: SkeletonPreview = {}): string {
    return '<div class="db-canvas-code" data-db-skeleton="1"></div>';
}
