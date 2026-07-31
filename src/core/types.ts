/**
 * The typed contract between the editor and the PHP side.
 *
 * Every shape here has a counterpart in `laravel/src`. Keeping them in one
 * place is what stops the builder from offering a column the renderer cannot
 * lay out.
 */

/** Horizontal alignment of a line-item column. */
export type ColumnAlign = 'left' | 'right' | 'center';

/**
 * How a value is formatted before it is printed. `text` is passed through,
 * everything else is formatted server-side so the preview and the PDF agree.
 */
export type ColumnFormat = 'text' | 'decimal' | 'integer' | 'currency';

/**
 * One column of the line-item table.
 *
 * `width` is mandatory on purpose: DomPDF's automatic table layout wraps
 * amounts onto two lines without explicit widths, so the editor must never
 * allow a column that has none.
 */
export interface LineItemColumn {
    key: string;
    label: string;
    /** A CSS width, e.g. `'14%'` or `'25mm'`. */
    width: string;
    align?: ColumnAlign;
    format?: ColumnFormat;
}

/** The columns a German business document shows by default. */
export const DEFAULT_COLUMNS: LineItemColumn[] = [
    { key: 'position', label: 'Pos.', width: '7%', align: 'right' },
    { key: 'description', label: 'Bezeichnung', width: '45%' },
    { key: 'quantity', label: 'Menge', width: '10%', align: 'right', format: 'decimal' },
    { key: 'unit', label: 'Einheit', width: '10%' },
    { key: 'unit_price', label: 'Einzelpreis', width: '14%', align: 'right', format: 'currency' },
    { key: 'total', label: 'Gesamt', width: '14%', align: 'right', format: 'currency' },
];

/** Page geometry in millimetres — mirrors `Data\PageSetup`. */
export interface PageSetup {
    paper: 'A4' | 'A5' | 'A3' | 'LETTER' | 'LEGAL';
    orientation: 'portrait' | 'landscape';
    marginTop: number;
    marginRight: number;
    marginBottom: number;
    marginLeft: number;
}

/** DIN 5008 form B — the default. */
export const DIN_5008: PageSetup = {
    paper: 'A4',
    orientation: 'portrait',
    marginTop: 16.9,
    marginRight: 20,
    marginBottom: 30,
    marginLeft: 24.1,
};

/**
 * The zones a template may edit.
 *
 * Everything outside this list belongs to the preset and is not editable —
 * on an invoice, the freedom to move the tax number is a liability, not a
 * feature.
 */
export type FreeZone = 'intro' | 'afterLineItems' | 'outro' | 'attachments';

/** A placeholder the editor offers in its variable picker. */
export interface PlaceholderDefinition {
    key: string;
    label: string;
    group?: string;
    example?: string;
}

/** Options handed to the editor when it is mounted. */
export interface DocumentBuilderOptions {
    container: HTMLElement;
    /** The stored design, or `null` for a fresh template. */
    design?: Record<string, unknown> | null;
    preset?: string;
    page?: Partial<PageSetup>;
    columns?: LineItemColumn[];
    placeholders?: PlaceholderDefinition[];
    locale?: 'de' | 'en';
    theme?: 'light' | 'dark';
    onChange?: (design: Record<string, unknown>, html: string) => void;
    onUploadImage?: (file: File) => Promise<string>;
    onReady?: () => void;
}
