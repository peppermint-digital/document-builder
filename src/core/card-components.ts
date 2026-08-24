import type { Editor } from 'grapesjs';

/**
 * Die Bausteine der Karte, die Bedeutung tragen statt Markup.
 *
 * Wie Positionstabelle und Summenblock zeigen sie im Canvas eine glaubwuerdige
 * Vorschau und geben beim Export ein einziges Zeichen aus. Der Grund ist
 * derselbe: was am Ende dasteht, weiss erst der Druck. Wie viele Workshops
 * jemand gebucht hat, kann eine Leinwand nicht ehrlich zeigen.
 */

export const CARD_ROWS_TYPE = 'db-card-rows';
export const CARD_CODE_TYPE = 'db-card-code';
export const CARD_IMAGE_TYPE = 'db-card-image';

/**
 * Vorschauinhalt ist unantastbar: nicht bearbeitbar, nicht verschiebbar, nicht
 * loeschbar. `selectable` bleibt bewusst offen — sonst waere der Baustein nur
 * ueber den Ebenenbaum erreichbar. Gleiche Begruendung wie bei `INERT` in
 * `components.ts`.
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
 * Ein leerer oder nur aus Leerzeichen bestehender Wert ist keine Angabe.
 *
 * Steht hier einmal, statt in jeder Komponente erneut: `'  '` als Gruppenname
 * ergaebe `{{ rows. }}` — ein Platzhalter, den die PHP-Seite nie ersetzt und
 * der als Text auf der Karte landet.
 */
function gesetzt(wert: unknown): string | null {
    const roh = String(wert ?? '').trim();

    return roh === '' ? null : roh;
}

/**
 * Das Zeichen fuer eine Zeilengruppe.
 *
 * Ohne Namen alle Zeilen, mit Namen genau eine Gruppe — und nichts, wenn die
 * Gruppe leer ist. Genau das macht die Ueberschrift im Block moeglich.
 */
export function rowsToken(gruppe: string | null | undefined): string {
    const name = gesetzt(gruppe);

    return name === null ? '{{ rows }}' : `{{ rows.${name} }}`;
}

/** Das Zeichen fuer einen Code. Ohne Schluessel der Hauptcode der Karte. */
export function codeToken(schluessel: string | null | undefined): string {
    const name = gesetzt(schluessel);

    return name === null ? '{{ code_image }}' : `{{ code_image.${name} }}`;
}

/** Das Zeichen fuer ein benanntes Bild. */
export function imageToken(schluessel: string | null | undefined): string {
    return `{{ image.${gesetzt(schluessel) ?? 'logo'} }}`;
}

/**
 * Welche Codes sich loeschen lassen duerfen.
 *
 * Der letzte nicht — ein Schild ohne Code ist am Einlass wertlos (#4433).
 * Als eigene Regel formuliert, weil sie sich so ohne Browser pruefen laesst;
 * `enforceCardCode()` traegt sie nur an die Komponenten.
 */
export function codeRemovability(anzahl: number): boolean {
    return anzahl > 1;
}

/** Beispielzeilen, damit der Block kein leerer Rahmen ist. */
const BEISPIEL_ZEILEN: Array<[string, string]> = [
    ['KI im Mittelstand', '10:30 Uhr'],
    ['Datenschutz in der Praxis', '14:00 Uhr'],
];

export function registerCardComponents(editor: Editor): void {
    registerRows(editor);
    registerCode(editor);
    registerImage(editor);
}

/**
 * Eine Zeilengruppe — `{{ rows }}` oder `{{ rows.workshops }}`.
 *
 * Die Ueberschrift gehoert IN den Block. Daneben in der Vorlage stuende sie
 * auch dann da, wenn niemand einen Workshop gebucht hat: die Zusatzfelder sind
 * ja vorhanden, eine blosse Bedingung auf „gibt es Zeilen?" haette also mit Ja
 * geantwortet. Genau daran ist Bug #630 entstanden.
 */
function registerRows(editor: Editor): void {
    editor.Components.addType(CARD_ROWS_TYPE, {
        isComponent: (el: HTMLElement) =>
            el?.getAttribute?.('data-db-block') === 'card-rows' ? { type: CARD_ROWS_TYPE } : undefined,
        model: {
            defaults: {
                tagName: 'div',
                name: 'Zeilengruppe',
                attributes: { 'data-db-block': 'card-rows', class: 'db-card-rows' },
                droppable: false,
                editable: false,
                traits: [
                    {
                        type: 'text',
                        name: 'gruppe',
                        label: 'Gruppe',
                        placeholder: 'z. B. workshops — leer: alle Zeilen',
                    },
                ],
            },

            init(this: any): void {
                this.on('change:gruppe', () => this.renderPreview());
                this.renderPreview();
            },

            /** Der Gruppenname, oder `null` fuer „alle Zeilen". */
            gruppe(this: any): string | null {
                return gesetzt(this.get('gruppe'));
            },

            renderPreview(this: any): void {
                const gruppe = this.gruppe();

                // Eine benannte Gruppe bekommt ihre Ueberschrift mit in die
                // Vorschau — sonst sieht der Nutzer nicht, dass sie zum Block
                // gehoert und mit ihm verschwindet.
                const kopf = gruppe === null
                    ? []
                    : [
                          {
                              tagName: 'div',
                              attributes: { class: 'db-card-rows-title' },
                              components: [text(`Überschrift der Gruppe „${gruppe}"`)],
                              ...INERT,
                          },
                      ];

                const zeilen = BEISPIEL_ZEILEN.map(([label, wert]) => ({
                    tagName: 'div',
                    attributes: { class: 'db-card-row', 'data-db-sample': '1' },
                    components: [
                        {
                            tagName: 'span',
                            attributes: { class: 'db-card-row-label' },
                            components: [text(label)],
                            ...INERT,
                        },
                        text(' '),
                        {
                            tagName: 'span',
                            attributes: { class: 'db-card-row-value' },
                            components: [text(wert)],
                            ...INERT,
                        },
                    ],
                    ...INERT,
                }));

                this.components([...kopf, ...zeilen]);
            },

            toHTML(this: any): string {
                return rowsToken(this.gruppe());
            },
        },
    });
}

/**
 * Der Code — `{{ code_image }}` oder `{{ code_image.workshop-1 }}`.
 *
 * Ohne Schluessel ist es der Hauptcode der Karte. Ein Schluessel waehlt einen
 * der Zusatzcodes; so bekommt jeder Workshop seinen eigenen QR, ohne dass es
 * je gebuchter Anzahl eine eigene Vorlage braucht.
 */
function registerCode(editor: Editor): void {
    editor.Components.addType(CARD_CODE_TYPE, {
        isComponent: (el: HTMLElement) =>
            el?.getAttribute?.('data-db-block') === 'card-code' ? { type: CARD_CODE_TYPE } : undefined,
        model: {
            defaults: {
                tagName: 'div',
                name: 'Code',
                attributes: { 'data-db-block': 'card-code', class: 'db-card-code' },
                droppable: false,
                editable: false,
                traits: [
                    {
                        type: 'text',
                        name: 'schluessel',
                        label: 'Schlüssel',
                        placeholder: 'leer: Hauptcode der Karte',
                    },
                ],
            },

            init(this: any): void {
                this.on('change:schluessel', () => this.renderPreview());
                this.renderPreview();
            },

            schluessel(this: any): string | null {
                return gesetzt(this.get('schluessel'));
            },

            renderPreview(this: any): void {
                const schluessel = this.schluessel();

                this.components([
                    {
                        tagName: 'div',
                        attributes: { class: 'db-card-code-box', 'data-db-sample': '1' },
                        components: [
                            text(schluessel === null ? 'Code der Karte' : `Code „${schluessel}"`),
                        ],
                        ...INERT,
                    },
                ]);
            },

            toHTML(this: any): string {
                return codeToken(this.schluessel());
            },
        },
    });
}

/** Ein benanntes Bild — `{{ image.logo }}`. Fehlt die Quelle, druckt es nichts. */
function registerImage(editor: Editor): void {
    editor.Components.addType(CARD_IMAGE_TYPE, {
        isComponent: (el: HTMLElement) =>
            el?.getAttribute?.('data-db-block') === 'card-image' ? { type: CARD_IMAGE_TYPE } : undefined,
        model: {
            defaults: {
                tagName: 'div',
                name: 'Bild',
                attributes: { 'data-db-block': 'card-image', class: 'db-card-image-slot' },
                droppable: false,
                editable: false,
                traits: [
                    { type: 'text', name: 'schluessel', label: 'Schlüssel', placeholder: 'z. B. logo' },
                ],
            },

            init(this: any): void {
                if (this.get('schluessel') === undefined || this.get('schluessel') === '') {
                    this.set('schluessel', 'logo', { silent: true });
                }

                this.on('change:schluessel', () => this.renderPreview());
                this.renderPreview();
            },

            schluessel(this: any): string {
                return gesetzt(this.get('schluessel')) ?? 'logo';
            },

            renderPreview(this: any): void {
                this.components([
                    {
                        tagName: 'div',
                        attributes: { class: 'db-card-image-box', 'data-db-sample': '1' },
                        components: [text(`Bild „${this.schluessel()}"`)],
                        ...INERT,
                    },
                ]);
            },

            toHTML(this: any): string {
                return imageToken(this.schluessel());
            },
        },
    });
}
