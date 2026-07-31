import type { Editor } from 'grapesjs';

/**
 * The blocks a user can place, in the order they appear in the sidebar.
 *
 * Deliberately short. Everything a business document actually needs is either
 * here or owned by the preset — a longer palette would only offer more ways to
 * build an invoice that does not print.
 */
export function registerBlocks(editor: Editor): void {
    const blocks = editor.BlockManager;

    blocks.add('db-text', {
        label: 'Text',
        category: 'content',
        media: icon('M4 6h16M4 10h16M4 14h10'),
        content: { type: 'text', content: 'Text bearbeiten …', tagName: 'p' },
    });

    blocks.add('db-heading', {
        label: 'Überschrift',
        category: 'content',
        media: icon('M5 5v14M15 5v14M5 12h10'),
        content: {
            type: 'text',
            tagName: 'h2',
            content: 'Überschrift',
            style: { 'font-size': '12pt', 'font-weight': 'bold', margin: '4mm 0 2mm 0' },
        },
    });

    blocks.add('db-image', {
        label: 'Bild',
        category: 'content',
        media: icon('M4 5h16v14H4zM4 15l4-4 4 4 3-3 5 5'),
        select: true,
        activate: true,
        content: { type: 'image', style: { 'max-width': '100%' } },
    });

    blocks.add('db-divider', {
        label: 'Trennlinie',
        category: 'content',
        media: icon('M4 12h16'),
        content: '<hr class="db-divider"/>',
    });

    blocks.add('db-spacer', {
        label: 'Abstand',
        category: 'layout',
        media: icon('M12 5v14M8 8l4-4 4 4M8 16l4 4 4-4'),
        content: { type: 'db-spacer' },
    });

    blocks.add('db-columns-2', {
        label: 'Zwei Spalten',
        category: 'layout',
        media: icon('M4 5h7v14H4zM13 5h7v14h-7z'),
        // A table rather than flexbox: DomPDF supports neither flex nor grid,
        // and a layout that only works in the canvas is a trap.
        content:
            '<table class="db-columns"><tbody><tr>' +
            '<td style="width: 50%"><p>Linke Spalte</p></td>' +
            '<td style="width: 50%"><p>Rechte Spalte</p></td>' +
            '</tr></tbody></table>',
    });

    blocks.add('db-line-items', {
        label: 'Positionstabelle',
        category: 'document',
        media: icon('M4 6h16M4 10h16M4 14h16M4 18h16'),
        content: { type: 'db-line-items' },
    });

    blocks.add('db-totals', {
        label: 'Summenblock',
        category: 'document',
        media: icon('M6 6h12M10 12h8M12 18h6'),
        content: { type: 'db-totals' },
    });

    blocks.add('db-page-break', {
        label: 'Seitenumbruch',
        category: 'layout',
        media: icon('M4 12h16M8 6l4-2 4 2M8 18l4 2 4-2'),
        content: { type: 'db-page-break' },
    });
}

function icon(path: string): string {
    return (
        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" ' +
        'stroke-linecap="round" stroke-linejoin="round" style="width:100%;height:22px">' +
        `<path d="${path}"/></svg>`
    );
}
