import type { Editor } from 'grapesjs';

import { DEFAULT_COLUMNS } from './defaults';
import { escapeHtml } from './theme';
import type { LineItemColumn } from './types';

/** Marks the two components that export as a token instead of as markup. */
export const LINE_ITEMS_TYPE = 'db-line-items';
export const TOTALS_TYPE = 'db-totals';

/** Sample rows shown in the canvas so the table is not an empty frame. */
const SAMPLE_ROWS: Array<Record<string, string>> = [
    {
        position: '1',
        description: 'Digitaldruck, 4/4-farbig',
        note: '135 g/m² Bilderdruck matt',
        quantity: '2,00',
        unit: 'Stk',
        unit_price: '12,50 €',
        total: '25,00 €',
    },
    {
        position: '2',
        description: 'Weiterverarbeitung, Rückenheftung',
        note: '',
        quantity: '1,00',
        unit: 'Pau',
        unit_price: '48,00 €',
        total: '48,00 €',
    },
];

function traitNameFor(column: LineItemColumn): string {
    return `col_${column.key}`;
}

/**
 * Sample content is inert: it cannot be edited, dragged or deleted.
 *
 * Without this a user could take the preview apart cell by cell and believe
 * they were editing the table — the table is built at print time, and nothing
 * they did here would survive.
 *
 * Note what is deliberately *not* set: `selectable`. Making the cells
 * unselectable means a click on the table selects nothing at all, and the only
 * way to reach its settings would be the layer tree. Clicks are allowed and
 * redirected to the table itself — see `redirectSelectionToOwner()`.
 */
const INERT = {
    editable: false,
    draggable: false,
    droppable: false,
    removable: false,
    copyable: false,
    layerable: false,
} as const;

function text(content: string): Record<string, unknown> {
    return { type: 'textnode', content };
}

/**
 * Builds the preview as a component tree rather than an HTML string.
 *
 * GrapesJS parses component HTML through a detached element, and a browser
 * drops `<tr>`/`<td>` that are not inside a real `<table>` — passing markup
 * here collapses every row into a single line of text. Definition objects skip
 * the parser entirely.
 */
function tableTree(
    columns: LineItemColumn[],
    rows: Array<Record<string, string>>,
): Array<Record<string, unknown>> {
    const cell = (
        tagName: 'th' | 'td',
        column: LineItemColumn,
        content: Array<Record<string, unknown>>,
    ): Record<string, unknown> => ({
        tagName,
        attributes: { class: `db-align-${column.align ?? 'left'}` },
        components: content,
        ...INERT,
    });

    return [
        {
            tagName: 'colgroup',
            components: columns.map((column) => ({
                tagName: 'col',
                attributes: { style: `width: ${column.width}` },
                ...INERT,
            })),
            ...INERT,
        },
        {
            tagName: 'thead',
            components: [
                {
                    tagName: 'tr',
                    components: columns.map((column) => cell('th', column, [text(column.label)])),
                    ...INERT,
                },
            ],
            ...INERT,
        },
        {
            tagName: 'tbody',
            components: rows.map((row) => ({
                tagName: 'tr',
                components: columns.map((column) => {
                    const value = row[column.key] ?? '';
                    const content: Array<Record<string, unknown>> = [text(value)];

                    if (column.key === 'description' && row.note) {
                        content.push({
                            tagName: 'span',
                            attributes: { class: 'db-note' },
                            components: [text(row.note)],
                            ...INERT,
                        });
                    }

                    return cell('td', column, content);
                }),
                ...INERT,
            })),
            ...INERT,
        },
    ];
}

/**
 * Registers the components that carry meaning rather than markup.
 *
 * Both of them render a realistic preview in the canvas and export a single
 * token. The actual table is laid out server-side at print time, because its
 * length is only known then — a table of unknown length is not something a
 * WYSIWYG canvas can honestly show.
 */
export function registerComponents(editor: Editor, availableColumns: LineItemColumn[]): void {
    registerLineItems(editor, availableColumns);
    registerTotals(editor);
    registerStructural(editor);
}

function registerLineItems(editor: Editor, availableColumns: LineItemColumn[]): void {
    const columns = availableColumns.length > 0 ? availableColumns : DEFAULT_COLUMNS;

    const traits = columns.map((column) => ({
        type: 'checkbox',
        name: traitNameFor(column),
        label: column.label,
        valueTrue: 'on',
        valueFalse: '',
    }));

    editor.Components.addType(LINE_ITEMS_TYPE, {
        isComponent: (el: HTMLElement) =>
            el?.getAttribute?.('data-db-block') === 'line-items' ? { type: LINE_ITEMS_TYPE } : undefined,
        model: {
            defaults: {
                tagName: 'table',
                name: 'Positionstabelle',
                attributes: { 'data-db-block': 'line-items', class: 'db-line-items' },
                // The user arranges it; the contents are not theirs to edit.
                droppable: false,
                editable: false,
                // One per document — a second one would print the same table twice.
                copyable: false,
                traits,
            },

            init(this: any): void {
                columns.forEach((column) => {
                    if (this.get(traitNameFor(column)) === undefined) {
                        this.set(traitNameFor(column), 'on', { silent: true });
                    }
                });

                // Only the column traits, never a blanket `change`. GrapesJS
                // writes selection and hover state onto the model too, so a
                // blanket listener rebuilds the preview on every click — which
                // destroys the very cell the user just selected.
                columns.forEach((column) => {
                    this.on(`change:${traitNameFor(column)}`, () => this.renderPreview());
                });

                this.renderPreview();
            },

            /** The columns the user left switched on, in their defined order. */
            activeColumns(this: any): LineItemColumn[] {
                const active = columns.filter((column) => Boolean(this.get(traitNameFor(column))));

                // Never let the table collapse to nothing — an empty colgroup
                // hands DomPDF back its automatic layout.
                return active.length > 0 ? active : columns;
            },

            renderPreview(this: any): void {
                this.components(tableTree(this.activeColumns(), SAMPLE_ROWS));
            },

            toHTML(): string {
                return '{{ line_items }}';
            },
        },
    });
}

function totalsRow(label: string, amount: string, rowClass = ''): Record<string, unknown> {
    return {
        tagName: 'tr',
        ...(rowClass === '' ? {} : { attributes: { class: rowClass } }),
        components: [
            { tagName: 'td', components: [text(label)], ...INERT },
            {
                tagName: 'td',
                attributes: { class: 'db-total-amount' },
                components: [text(amount)],
                ...INERT,
            },
        ],
        ...INERT,
    };
}

function registerTotals(editor: Editor): void {
    editor.Components.addType(TOTALS_TYPE, {
        isComponent: (el: HTMLElement) =>
            el?.getAttribute?.('data-db-block') === 'totals' ? { type: TOTALS_TYPE } : undefined,
        model: {
            defaults: {
                tagName: 'table',
                name: 'Summenblock',
                attributes: { 'data-db-block': 'totals', class: 'db-totals' },
                droppable: false,
                editable: false,
                copyable: false,
                // Same reason as the line-item table: a markup string would be
                // parsed outside a <table> and lose every row.
                components: [
                    {
                        tagName: 'tbody',
                        components: [
                            totalsRow('Zwischensumme', '73,00 €'),
                            totalsRow('zzgl. 19 % USt.', '13,87 €'),
                            totalsRow('Gesamtbetrag', '86,87 €', 'db-total-gross'),
                        ],
                        ...INERT,
                    },
                ],
            },

            toHTML(): string {
                return '{{ totals }}';
            },
        },
    });
}

/**
 * Structural blocks that do export as markup, but whose innards are not the
 * user's to edit.
 */
function registerStructural(editor: Editor): void {
    editor.Components.addType('db-page-break', {
        isComponent: (el: HTMLElement) =>
            el?.classList?.contains('db-page-break') ? { type: 'db-page-break' } : undefined,
        model: {
            defaults: {
                tagName: 'div',
                name: 'Seitenumbruch',
                attributes: { class: 'db-page-break' },
                droppable: false,
                editable: false,
                components: 'Seitenumbruch',
            },

            toHTML(): string {
                return '<div class="db-page-break"></div>';
            },
        },
    });

    editor.Components.addType('db-spacer', {
        isComponent: (el: HTMLElement) =>
            el?.classList?.contains('db-spacer') ? { type: 'db-spacer' } : undefined,
        model: {
            defaults: {
                tagName: 'div',
                name: 'Abstand',
                attributes: { class: 'db-spacer' },
                style: { height: '6mm' },
                droppable: false,
                editable: false,
                traits: [{ type: 'text', name: 'height', label: 'Höhe', placeholder: '6mm' }],
            },
        },
    });
}
