/**
 * GrapesJS locale messages.
 *
 * These have to be part of the init config: GrapesJS renders its panels during
 * init, so messages added afterwards arrive too late and the buttons keep their
 * English titles.
 */
export const locales = {
    de: {
        assetManager: {
            addButton: 'Bild hinzufügen',
            inputPlh: 'https://…/bild.jpg',
            modalTitle: 'Bild auswählen',
            uploadTitle: 'Datei hier ablegen oder klicken zum Hochladen',
        },
        blockManager: {
            labels: {
                'db-text': 'Text',
                'db-heading': 'Überschrift',
                'db-image': 'Bild',
                'db-divider': 'Trennlinie',
                'db-spacer': 'Abstand',
                'db-columns-2': 'Zwei Spalten',
                'db-line-items': 'Positionstabelle',
                'db-totals': 'Summenblock',
                'db-page-break': 'Seitenumbruch',
            },
            categories: {
                content: 'Inhalt',
                document: 'Dokument',
                layout: 'Aufbau',
            },
        },
        domComponents: {
            names: {
                '': 'Element',
                text: 'Text',
                image: 'Bild',
            },
        },
        panels: {
            buttons: {
                titles: {
                    'sw-visibility': 'Hilfslinien anzeigen',
                    'open-sm': 'Gestaltung',
                    'open-tm': 'Einstellungen',
                    'open-layers': 'Ebenen',
                    'open-blocks': 'Bausteine',
                },
            },
        },
        selectorManager: {
            label: 'Klassen',
            selected: 'Ausgewählt',
            emptyState: '– Element –',
            states: {
                hover: 'Mauszeiger darüber',
                'nth-of-type(2n)': 'jede zweite',
            },
        },
        styleManager: {
            empty: 'Wähle ein Element aus, um es zu gestalten.',
            layer: 'Ebene',
            fileButton: 'Bild',
            sectors: {
                typography: 'Schrift',
                decorations: 'Gestaltung',
                dimension: 'Maße',
            },
            properties: {
                'font-family': 'Schriftart',
                'font-size': 'Schriftgröße',
                'font-weight': 'Schriftschnitt',
                'text-align': 'Ausrichtung',
                color: 'Farbe',
                'background-color': 'Hintergrund',
                'margin-top': 'Abstand oben',
                'margin-bottom': 'Abstand unten',
                'padding-top': 'Innenabstand oben',
                'padding-bottom': 'Innenabstand unten',
                'border-color': 'Rahmenfarbe',
                'border-width': 'Rahmenstärke',
            },
        },
        traitManager: {
            empty: 'Wähle ein Element aus, um seine Einstellungen zu sehen.',
            label: 'Einstellungen',
            traits: {
                labels: {
                    alt: 'Bildbeschreibung',
                    href: 'Verweisziel',
                },
            },
        },
    },
    en: {
        blockManager: {
            labels: {
                'db-text': 'Text',
                'db-heading': 'Heading',
                'db-image': 'Image',
                'db-divider': 'Divider',
                'db-spacer': 'Spacer',
                'db-columns-2': 'Two columns',
                'db-line-items': 'Line items',
                'db-totals': 'Totals',
                'db-page-break': 'Page break',
            },
            categories: {
                content: 'Content',
                document: 'Document',
                layout: 'Layout',
            },
        },
    },
};
