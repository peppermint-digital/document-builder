import type { Component, Editor } from 'grapesjs';

import type { PresetName, ZoneName } from './types';

export const ZONE_TYPE = 'db-zone';

/** Reihenfolge im Canvas — sie bestimmt auch die Reihenfolge im Ebenenbaum. */
export const ZONES: ZoneName[] = ['header', 'body', 'footer'];

/**
 * Wie die drei Bereiche heissen. Der Aufbau ist derselbe, die Begriffe sind es
 * nicht: „Briefkopf" auf einem Namensschild waere schlicht falsch, und ein
 * Name im Ebenenbaum, der nicht zur Sache passt, kostet jede Suche danach.
 */
const LABELS: Record<PresetName, Record<ZoneName, string>> = {
    din5008: {
        header: 'Briefkopf',
        body: 'Inhalt',
        footer: 'Fußzeile',
    },
    card: {
        header: 'Kartenkopf',
        body: 'Karteninhalt',
        footer: 'Kartenfuß',
    },
};

/**
 * Die drei Bereiche des Blattes als feste, nicht löschbare Behälter.
 *
 * Ihre Position und Höhe gehören dem Preset — verschieben lässt sich hier
 * nichts. Was hineinkommt, bestimmt die Vorlage. Genau diese Trennung ist der
 * Grund, warum der Baukasten auf einer Rechnung verantwortbar ist.
 */
export function registerZones(editor: Editor): void {
    editor.Components.addType(ZONE_TYPE, {
        isComponent: (el: HTMLElement) => {
            const zone = el?.getAttribute?.('data-db-zone');

            return zone ? { type: ZONE_TYPE, zone } : undefined;
        },
        model: {
            defaults: {
                tagName: 'div',
                droppable: true,
                draggable: false,
                removable: false,
                copyable: false,
                selectable: false,
                hoverable: false,
                // Im Ebenenbaum sichtbar zu lassen ist der einzige Weg, eine
                // leere Zone gezielt anzusteuern.
                layerable: true,
            },
        },
    });
}

/**
 * Baut die drei Zonen mit dem übergebenen Inhalt auf.
 *
 * @param contents HTML je Zone; fehlende Zonen bleiben leer.
 * @param preset Bestimmt die Beschriftung im Ebenenbaum.
 */
export function buildZones(
    contents: Partial<Record<ZoneName, string>>,
    preset: PresetName = 'din5008',
): string {
    const labels = LABELS[preset] ?? LABELS.din5008;

    return ZONES.map((zone) => {
        const inner = contents[zone] ?? '';

        return (
            `<div data-db-zone="${zone}" class="db-zone db-zone-${zone}" data-gjs-name="${labels[zone]}">` +
            inner +
            '</div>'
        );
    }).join('');
}

/** Der Behälter einer Zone, oder `undefined` wenn er noch nicht steht. */
export function findZone(editor: Editor, zone: ZoneName): Component | undefined {
    return editor.Components.getWrapper()
        ?.findType(ZONE_TYPE)
        ?.find((component) => component.getAttributes()['data-db-zone'] === zone);
}

/**
 * Der Inhalt einer Zone als HTML — ohne den Behälter selbst, denn der gehört
 * dem Preset und nicht dem gespeicherten Design.
 */
export function zoneHtml(editor: Editor, zone: ZoneName): string {
    const container = findZone(editor, zone);

    if (!container) {
        return '';
    }

    return container
        .components()
        .map((child: Component) => child.toHTML())
        .join('')
        .trim();
}
