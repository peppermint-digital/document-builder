import { fromPaperLeft, fromPaperTop, paperSize } from './defaults';
import type { PageSetup, SkeletonPreview } from './types';

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
            padding-top: ${page.marginTop + bodyOffset}mm;
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

        .db-canvas-footer {
            position: absolute;
            left: ${page.marginLeft}mm;
            right: ${page.marginRight}mm;
            bottom: 8mm;
            height: 18mm;
            border-top: 0.3mm dashed #cbd5e1;
            padding-top: 1.5mm;
            font-size: 7pt;
            color: #94a3b8;
            pointer-events: none;
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
        '<div class="db-canvas-footer" data-db-skeleton="1">Fußzeile mit Pflichtangaben — vom Dokument gefüllt</div>',
    ].join('');
}

export function escapeHtml(value: string): string {
    return value
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}
