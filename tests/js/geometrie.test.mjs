/**
 * Die Leinwand muss dieselbe Karte zeichnen, die der Renderer druckt.
 *
 * Das ist keine Stilfrage: weicht die Vorschau ab, ist sie schlimmer als keine
 * Vorschau — sie behauptet etwas ueber das Papier, das nicht stimmt, und der
 * Fehler faellt erst auf, wenn ein Stapel Namensschilder aus dem Drucker kommt.
 */
import { strict as assert } from 'node:assert';
import { describe, it } from 'node:test';

import {
    CARD_DEFAULT,
    CARD_TITLE_EM,
    cardBaseFontPt,
    cardGridSize,
    cardInnerSize,
    cardPaddingMm,
    cardPosition,
    cardPreset,
    din5008Preset,
    DIN_5008,
    resolvePreset,
} from './.bundle/kern.mjs';
import { phpGeometrie } from './geometrie-php-abgleich.mjs';

/** Die drei Formate aus Connect, plus zwei Faelle, die die Rechnung reizen. */
const FAELLE = {
    umhaengeschild: { ...CARD_DEFAULT, cardWidth: 76, cardHeight: 124 },
    ausweiskarte: { ...CARD_DEFAULT, cardWidth: 86, cardHeight: 54 },
    grossesSchild: { ...CARD_DEFAULT, cardWidth: 90, cardHeight: 130 },
    ticket: { ...CARD_DEFAULT, cardWidth: 130, cardHeight: 158, fontPt: 11 },
    bogenMitRahmen: {
        ...CARD_DEFAULT,
        // 15mm Rand, weil 2 x 86mm + 4mm Rinne auf A4 mit 20mm nicht passen —
        // der Riegel in SheetSetup hat genau das beim ersten Lauf gemeldet.
        margin: 15,
        cardWidth: 86,
        cardHeight: 54,
        columns: 2,
        rows: 4,
        gutterX: 4,
        gutterY: 3,
        paddingEm: 0.6,
        borderMm: 0.4,
    },
};

describe('Schriftableitung aus der Kartenhoehe', () => {
    it('trifft die beiden von Hand gesetzten Namensschilder', () => {
        // Die Eichwerte aus dem PHP-Kommentar. Sie sind der Grund, warum die
        // Ableitung die Wurzel nimmt und keine Proportion.
        assert.equal(cardBaseFontPt({ ...CARD_DEFAULT, cardHeight: 124 }), 12.8);
        assert.equal(cardBaseFontPt({ ...CARD_DEFAULT, cardHeight: 54 }), 8.5);

        // Titel bei 2.2em — das ergibt 28.2pt bzw. 18.7pt gegen die von Hand
        // gesetzten 28 und 18.
        assert.equal(Math.round(12.8 * CARD_TITLE_EM * 10) / 10, 28.2);
        assert.equal(Math.round(8.5 * CARD_TITLE_EM * 10) / 10, 18.7);
    });

    it('laesst einen Entwurf die Ableitung ueberschreiben', () => {
        assert.equal(cardBaseFontPt({ ...CARD_DEFAULT, cardHeight: 158, fontPt: 11 }), 11);
    });

    it('ignoriert eine unsinnige Vorgabe und leitet ab', () => {
        // Sonst schriebe eine 0 die Karte unlesbar, statt den Fehler zu zeigen.
        assert.equal(cardBaseFontPt({ ...CARD_DEFAULT, cardHeight: 124, fontPt: 0 }), 12.8);
    });
});

describe('Innenflaeche', () => {
    it('zieht Innenabstand und Rahmen ab, statt sich auf box-sizing zu verlassen', () => {
        const karte = { ...CARD_DEFAULT, cardWidth: 86, cardHeight: 54, paddingEm: 0.6, borderMm: 0.4 };
        const polster = cardPaddingMm(karte);
        const innen = cardInnerSize(karte);

        assert.ok(polster > 0, 'Innenabstand muss in Millimeter umgerechnet werden');
        assert.equal(innen.width, 86 - 2 * (polster + 0.4));
        assert.equal(innen.height, 54 - 2 * (polster + 0.4));
    });

    it('laesst die Karte unveraendert, wenn es weder Rahmen noch Polster gibt', () => {
        const innen = cardInnerSize({ ...CARD_DEFAULT, cardWidth: 76, cardHeight: 124 });

        assert.equal(innen.width, 76);
        assert.equal(innen.height, 124);
    });
});

describe('Raster', () => {
    it('setzt die Karten spaltenweise', () => {
        const bogen = { ...CARD_DEFAULT, cardWidth: 86, cardHeight: 54, columns: 2, rows: 4, gutterX: 4, gutterY: 3 };

        assert.deepEqual(cardPosition(bogen, 0), { left: 0, top: 0 });
        assert.deepEqual(cardPosition(bogen, 1), { left: 90, top: 0 });
        assert.deepEqual(cardPosition(bogen, 2), { left: 0, top: 57 });
        assert.deepEqual(cardPosition(bogen, 7), { left: 90, top: 171 });
    });

    it('rechnet die Rinnen in die Rasterbreite ein, aber nicht hinter die letzte Karte', () => {
        const bogen = { ...CARD_DEFAULT, cardWidth: 86, cardHeight: 54, columns: 2, rows: 4, gutterX: 4, gutterY: 3 };

        assert.equal(cardGridSize(bogen).width, 86 * 2 + 4);
        assert.equal(cardGridSize(bogen).height, 54 * 4 + 3 * 3);
    });
});

describe('Abgleich mit dem Renderer', () => {
    const php = phpGeometrie(FAELLE);

    for (const [name, karte] of Object.entries(FAELLE)) {
        it(`${name}: Leinwand und PDF rechnen gleich`, () => {
            const innen = cardInnerSize(karte);

            assert.equal(innen.width, php[name].innerWidth, 'Innenbreite');
            assert.equal(innen.height, php[name].innerHeight, 'Innenhoehe');
            assert.equal(cardPaddingMm(karte), php[name].paddingMm, 'Innenabstand');
            assert.equal(cardBaseFontPt(karte), php[name].baseFontPt, 'Basisschrift');
            assert.equal(CARD_TITLE_EM, php[name].titleEm, 'Titelgroesse');
            assert.equal(cardGridSize(karte).width, php[name].gridWidth, 'Rasterbreite');
            assert.equal(karte.columns * karte.rows, php[name].perPage, 'Karten je Bogen');
        });
    }
});

describe('Presets', () => {
    it('bleibt ohne Angabe bei DIN 5008', () => {
        assert.equal(resolvePreset(undefined, DIN_5008, undefined).name, 'din5008');
    });

    it('waehlt das Kartengeruest, wenn es verlangt wird', () => {
        assert.equal(resolvePreset('card', DIN_5008, { cardWidth: 86, cardHeight: 54 }).name, 'card');
    });

    it('zeichnet die Leinwand auf das Kartenmass, nicht auf ein Papierformat', () => {
        const css = cardPreset({ ...CARD_DEFAULT, cardWidth: 86, cardHeight: 54 }).canvasCss({});

        assert.match(css, /width: 86mm/);
        assert.match(css, /min-height: 54mm/);
        // Die Gegenprobe: kein Papierformat hat sich eingeschlichen.
        assert.doesNotMatch(css, /210mm|297mm/);
    });

    it('haelt die Kartenklassen mit dem Renderer gleich', () => {
        const css = cardPreset(CARD_DEFAULT).canvasCss({});

        for (const klasse of ['db-card-title', 'db-card-subtitle', 'db-card-rows', 'db-card-code']) {
            assert.match(css, new RegExp(`\\.${klasse}`), `${klasse} fehlt auf der Leinwand`);
        }
    });

    it('beschriftet den Code-Platzhalter, statt einen leeren Kasten zu zeigen', () => {
        // Ein unbeschrifteter Kasten auf der Karte erklaert niemandem, was dort
        // spaeter steht. Die erste Fassung legte `content` auf das `div` selbst
        // — syntaktisch tadellos, gerendert nichts. Der Pruefstand fand es,
        // dieser Test haelt es fest.
        const css = cardPreset(CARD_DEFAULT).canvasCss({});

        assert.match(css, /\.db-canvas-code::after \{[^}]*content:/s, 'Beschriftung gehoert ans Pseudo-Element');
    });

    it('haelt den Code-Platz bei 18mm', () => {
        // 25mm passten nicht mehr, sobald Ueberschrift und Trennlinie dazukamen
        // (#4432). Die Zahl ist eine Entscheidung, kein Zufall.
        assert.match(cardPreset(CARD_DEFAULT).canvasCss({}), /\.db-canvas-code \{[^}]*width: 18mm/s);
    });

    it('laesst das DIN-Geruest unveraendert', () => {
        const css = din5008Preset(DIN_5008).canvasCss({});

        assert.match(css, /width: 210mm/);
        assert.match(css, /Anschriftfeld/);
    });

    it('gibt der Karte einen anderen Startinhalt als dem Brief', () => {
        assert.match(cardPreset(CARD_DEFAULT).starterBody(), /db-card-title/);
        assert.doesNotMatch(cardPreset(CARD_DEFAULT).starterBody(), /line-items/);
        assert.match(din5008Preset(DIN_5008).starterBody(), /line-items/);
    });
});
