import type { Editor } from 'grapesjs';

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

/** Page geometry in millimetres — mirrors `Data\PageSetup`. */
export interface PageSetup {
    paper: 'A4' | 'A5' | 'A3' | 'LETTER' | 'LEGAL';
    orientation: 'portrait' | 'landscape';
    marginTop: number;
    marginRight: number;
    marginBottom: number;
    marginLeft: number;
}

/** A placeholder the editor offers in its variable picker. */
export interface PlaceholderDefinition {
    key: string;
    label: string;
    group?: string;
    example?: string;
}

/**
 * What the editor hands back for storage.
 *
 * `html` is the body for the free zones — the thing the PHP side prints.
 * `project` is the editable design. `columns` is the line-item configuration,
 * which lives beside the HTML rather than inside it: the body only ever says
 * `{{ line_items }}`, and how that table is built is structured data.
 */
export interface DocumentDesign {
    /** Der Briefkopf über dem Anschriftfeld. Nur erste Seite. */
    header: string;
    /** Der freie Mittelteil zwischen Betreffzeile und Seitenende. */
    body: string;
    /** Die Fußzeile mit den Pflichtangaben. Auf jeder Seite. */
    footer: string;
    project: Record<string, unknown>;
    columns: LineItemColumn[];
    /**
     * @deprecated Frühere Fassungen hatten nur ein HTML-Feld. Wird beim Laden
     * noch als Rumpf akzeptiert, damit gespeicherte Entwürfe nicht verfallen.
     */
    html?: string;
}

/** Die drei bearbeitbaren Bereiche des Blattes. */
export type ZoneName = 'header' | 'body' | 'footer';

/** Options handed to the editor when it is mounted. */
export interface DocumentBuilderOptions {
    container: HTMLElement;
    /** A previously stored design, or `undefined` for a fresh template. */
    design?: DocumentDesign | null;
    page?: Partial<PageSetup>;
    /** Columns offered in the line-item trait panel. */
    availableColumns?: LineItemColumn[];
    placeholders?: PlaceholderDefinition[] | Record<string, string>;
    locale?: 'de' | 'en';
    theme?: 'light' | 'dark';
    /** Rendered behind the free zone so the user sees a realistic page. */
    skeletonPreview?: SkeletonPreview;
    /**
     * Inhalte, mit denen leere Zonen beim ersten Öffnen vorbelegt werden.
     *
     * Vor allem für die Fußzeile gedacht: wer nichts anfasst, soll trotzdem
     * korrekte Pflichtangaben bekommen, statt sie in jeder Vorlage neu zu
     * tippen.
     */
    defaults?: Partial<Record<ZoneName, string>>;
    onChange?: (design: DocumentDesign) => void;
    onUploadImage?: (file: File) => Promise<string>;
    onReady?: (instance: DocumentBuilderInstance) => void;
    /** Escape hatch for raw GrapesJS config. */
    grapesConfig?: Record<string, unknown>;
}

/**
 * The parts of the page the preset owns. Shown in the canvas as context, never
 * editable — a user must be able to see where their text lands relative to the
 * address field without being able to move the address field.
 */
export interface SkeletonPreview {
    recipientLines?: string[];
    metaLines?: Array<{ label: string; value: string }>;
    subject?: string;
    footerColumns?: string[][];
    logoUrl?: string;
}

export interface DocumentBuilderInstance {
    editor: Editor;
    /** The body HTML — the free zone between subject line and footer. */
    getHtml(): string;
    /** HTML of a single zone. */
    getZone(zone: ZoneName): string;
    /** Everything worth storing. */
    getDesign(): DocumentDesign;
    /** Columns currently configured on the line-item table. */
    getColumns(): LineItemColumn[];
    loadDesign(design: DocumentDesign | null): void;
    insertPlaceholder(key: string): void;
    isEmpty(): boolean;
    destroy(): void;
}
