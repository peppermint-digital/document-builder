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

                this.on('change', () => this.renderPreview());
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
                const active = this.activeColumns();

                const head = active
                    .map(
                        (column: LineItemColumn) =>
                            `<th class="db-align-${column.align ?? 'left'}">${escapeHtml(column.label)}</th>`,
                    )
                    .join('');

                const body = SAMPLE_ROWS.map((row) => {
                    const cells = active
                        .map((column: LineItemColumn) => {
                            const value = escapeHtml(row[column.key] ?? '');
                            const note =
                                column.key === 'description' && row.note
                                    ? `<span class="db-note">${escapeHtml(row.note)}</span>`
                                    : '';

                            return `<td class="db-align-${column.align ?? 'left'}">${value}${note}</td>`;
                        })
                        .join('');

                    return `<tr>${cells}</tr>`;
                }).join('');

                const cols = active
                    .map((column: LineItemColumn) => `<col style="width: ${column.width}">`)
                    .join('');

                this.components(
                    `<colgroup data-db-sample="1">${cols}</colgroup>` +
                        `<thead data-db-sample="1"><tr>${head}</tr></thead>` +
                        `<tbody data-db-sample="1">${body}</tbody>`,
                );
            },

            toHTML(): string {
                return '{{ line_items }}';
            },
        },
    });
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
                components:
                    '<tbody data-db-sample="1">' +
                    '<tr><td>Zwischensumme</td><td class="db-total-amount">73,00 €</td></tr>' +
                    '<tr><td>zzgl. 19 % USt.</td><td class="db-total-amount">13,87 €</td></tr>' +
                    '<tr class="db-total-gross"><td>Gesamtbetrag</td><td class="db-total-amount">86,87 €</td></tr>' +
                    '</tbody>',
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
