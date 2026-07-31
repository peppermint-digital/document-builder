import grapesjs from 'grapesjs';
import type { Component, Editor } from 'grapesjs';

import { registerBlocks } from './blocks';
import { LINE_ITEMS_TYPE, registerComponents, TOTALS_TYPE } from './components';
import { DEFAULT_COLUMNS, DIN_5008 } from './defaults';
import { locales } from './i18n';
import { BRAND_COLORS, canvasCss, FONT_STACKS, skeletonHtml } from './theme';
import type {
    DocumentBuilderInstance,
    DocumentBuilderOptions,
    DocumentDesign,
    LineItemColumn,
    PageSetup,
    ZoneName,
} from './types';
import { buildZones, registerZones, zoneHtml } from './zones';
import { insertPlaceholder, normalizePlaceholders, registerPlaceholderRteAction } from './variables';

/** Womit der Mittelteil einer frischen Vorlage beginnt. */
const STARTER_BODY =
    '<p>Sehr geehrte Damen und Herren,</p>' +
    '<p>vielen Dank für Ihre Anfrage. Gern unterbreiten wir Ihnen folgendes Angebot.</p>' +
    '<table data-db-block="line-items" class="db-line-items"></table>' +
    '<table data-db-block="totals" class="db-totals"></table>' +
    '<p>Wir freuen uns auf Ihre Rückmeldung.</p>';

export function createDocumentBuilder(options: DocumentBuilderOptions): DocumentBuilderInstance {
    const {
        container,
        design,
        page: pageOverrides = {},
        availableColumns = DEFAULT_COLUMNS,
        placeholders,
        locale = 'de',
        theme = 'light',
        skeletonPreview = {},
        defaults = {},
        onUploadImage,
        onChange,
        onReady,
        grapesConfig = {},
    } = options;

    const page: PageSetup = { ...DIN_5008, ...pageOverrides };
    const normalizedPlaceholders = normalizePlaceholders(placeholders);

    container.classList.add('pm-document-builder', `pm-document-builder--${theme}`);

    const editor = grapesjs.init({
        container,
        height: '100%',
        width: 'auto',
        fromElement: false,
        // The host app owns persistence — it gets the design and decides.
        storageManager: false,
        undoManager: { trackSelection: false },
        canvasCss: canvasCss(page, skeletonPreview),
        // Ein Dokument hat genau ein Format: das Blatt. Breakpoints gibt es im
        // Druck nicht. Die Leiste selbst wird in styles.css ausgeblendet —
        // `Panels.removePanel('devices-c')` greift nicht, GrapesJS baut sie
        // beim Rendern erneut auf und zeigt dann ein leeres Auswahlfeld.
        deviceManager: { devices: [] },
        colorPicker: { palette: [BRAND_COLORS] },
        assetManager: {
            upload: false,
            autoAdd: true,
            uploadFile: onUploadImage
                ? async (event: DragEvent): Promise<void> => {
                      const input = event.target as HTMLInputElement | null;
                      const files = event.dataTransfer?.files ?? input?.files;

                      if (!files?.length) {
                          return;
                      }

                      try {
                          const urls = await Promise.all(Array.from(files).map((file) => onUploadImage(file)));
                          editor.AssetManager.add(urls);
                      } catch (error: unknown) {
                          editor.log(
                              error instanceof Error ? error.message : 'Bild konnte nicht hochgeladen werden.',
                              { level: 'error' },
                          );
                      }
                  }
                : undefined,
        },
        // Locale has to be part of the init config: GrapesJS renders its panels
        // during init, so messages added afterwards arrive too late and the
        // buttons keep their English titles.
        i18n: {
            locale: locale in locales ? locale : 'en',
            detectLocale: false,
            messages: locales,
        },
        ...grapesConfig,
    });

    // Print-safe stacks only. A font DomPDF has no file for silently becomes
    // Helvetica, which is worse than not offering it.
    const fontProperty = editor.StyleManager.getSector('typography')?.getProperty('font-family');
    if (fontProperty) {
        fontProperty.set('options', FONT_STACKS);
    }

    registerZones(editor);
    registerComponents(editor, availableColumns);
    registerBlocks(editor);
    registerPlaceholderRteAction(editor, normalizedPlaceholders);
    enforceSingleInstance(editor);
    redirectSelectionToOwner(editor);

    const instance: DocumentBuilderInstance = {
        editor,

        getZone(zone: ZoneName): string {
            const html = zoneHtml(editor, zone);

            if (zone !== 'body') {
                return html;
            }

            // Eigene Stilregeln hängen am Rumpf. Sie einmal auszugeben reicht —
            // das Preset setzt alle drei Zonen in dasselbe Dokument.
            const css = String(editor.getCss({ avoidProtected: true }) ?? '').trim();

            return css === '' ? html : `<style>${css}</style>${html}`;
        },

        getHtml(): string {
            return this.getZone('body');
        },

        getColumns(): LineItemColumn[] {
            const table = findByType(editor, LINE_ITEMS_TYPE);

            return (table as unknown as { activeColumns?: () => LineItemColumn[] })?.activeColumns?.() ?? [];
        },

        getDesign(): DocumentDesign {
            return {
                header: this.getZone('header'),
                body: this.getZone('body'),
                footer: this.getZone('footer'),
                project: editor.getProjectData() as Record<string, unknown>,
                columns: this.getColumns(),
            };
        },

        loadDesign(next: DocumentDesign | null): void {
            if (next?.project && Object.keys(next.project).length > 0) {
                editor.loadProjectData(next.project);
            } else {
                editor.Components.getWrapper()?.set('content', '');
                editor.setComponents(
                    buildZones({
                        header: next?.header ?? defaults.header ?? '',
                        // `html` ist die alte Einfeld-Fassung: als Rumpf lesen,
                        // damit gespeicherte Entwürfe nicht verfallen.
                        body: next?.body ?? next?.html ?? STARTER_BODY,
                        footer: next?.footer ?? defaults.footer ?? '',
                    }),
                );
            }

            editor.UndoManager.clear();
            paintSkeleton(editor, skeletonPreview);
        },

        insertPlaceholder(key: string): void {
            insertPlaceholder(editor, key);
        },

        isEmpty(): boolean {
            const body = editor.Components.getWrapper()?.components();

            return !body || body.length === 0;
        },

        destroy(): void {
            delete (container as HTMLElement & { documentBuilder?: DocumentBuilderInstance }).documentBuilder;
            editor.destroy();
            container.classList.remove('pm-document-builder', `pm-document-builder--${theme}`);
        },
    };

    // Handle on the mounted element. Host apps get the instance through
    // onReady, but a DOM-reachable reference is what makes the editor
    // debuggable from a console and drivable from an end-to-end test.
    (container as HTMLElement & { documentBuilder?: DocumentBuilderInstance }).documentBuilder = instance;

    instance.loadDesign(design ?? null);

    if (onChange) {
        editor.on('update', () => onChange(instance.getDesign()));
    }

    editor.onReady(() => {
        paintSkeleton(editor, skeletonPreview);
        onReady?.(instance);
    });

    return instance;
}

/**
 * The line-item table and the totals block exist at most once. A second one
 * would print the same table twice, so the drop is undone rather than
 * explained after the fact.
 */
function enforceSingleInstance(editor: Editor): void {
    editor.on('component:add', (component: Component) => {
        const type = component.get('type');

        if (type !== LINE_ITEMS_TYPE && type !== TOTALS_TYPE) {
            return;
        }

        const existing = editor.Components.getWrapper()
            ?.findType(String(type))
            ?.filter((found) => found !== component);

        if (existing && existing.length > 0) {
            component.remove();
            editor.log(
                type === LINE_ITEMS_TYPE
                    ? 'Eine Positionstabelle je Dokument.'
                    : 'Einen Summenblock je Dokument.',
                { level: 'warning' },
            );
        }
    });
}

/**
 * A click inside the preview selects the table, not the cell.
 *
 * The sample rows are scaffolding, not content. Selecting a `<td>` would show
 * an empty settings panel and invite the user to style something that is
 * regenerated on every change — the table itself is what has settings.
 */
function redirectSelectionToOwner(editor: Editor): void {
    let redirecting = false;

    editor.on('component:selected', (component: Component) => {
        if (redirecting) {
            return;
        }

        const owner =
            component.closestType?.(LINE_ITEMS_TYPE) ?? component.closestType?.(TOTALS_TYPE);

        if (!owner || owner === component) {
            return;
        }

        // Deferred on purpose: this event fires *during* GrapesJS's own
        // selection routine, and selecting re-entrantly is overwritten the
        // moment that routine finishes. The redirect has to land after it.
        redirecting = true;
        setTimeout(() => {
            editor.select(owner);
            redirecting = false;
        }, 0);
    });
}

function findByType(editor: Editor, type: string): Component | undefined {
    return editor.Components.getWrapper()?.findType(type)?.[0];
}

/**
 * Draws the preset-owned zones into the canvas document.
 *
 * Deliberately written straight into the iframe rather than added as
 * components: decoration that is part of the component tree would end up in the
 * export, and a user could select and delete it. This way it is visible,
 * inert and impossible to save.
 */
function paintSkeleton(editor: Editor, preview: Parameters<typeof skeletonHtml>[0]): void {
    const doc = editor.Canvas.getDocument();

    if (!doc?.body) {
        return;
    }

    doc.body.querySelectorAll('[data-db-skeleton]').forEach((node) => node.remove());
    doc.body.insertAdjacentHTML('beforeend', skeletonHtml(preview));
}
