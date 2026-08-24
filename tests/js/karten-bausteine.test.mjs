/**
 * Was der Editor auf einer Karte anbietet — und was nicht.
 *
 * Die Leiste ist eine Aussage darueber, was das Werkzeug kann. Bietet sie eine
 * Positionstabelle auf einem Namensschild an, ist das kein harmloses Zuviel,
 * sondern eine Einladung, eine Karte zu bauen, die nicht druckt.
 */
import { strict as assert } from 'node:assert';
import { describe, it } from 'node:test';

import {
    buildZones,
    codeRemovability,
    codeToken,
    imageToken,
    registerBlocks,
    registerCardBlocks,
    rowsToken,
} from './.bundle/kern.mjs';

/** Ein Editor-Doppel, das nur mitschreibt, was registriert wird. */
function bausteineVon(registrieren) {
    const gesammelt = [];
    registrieren({ BlockManager: { add: (id, def) => gesammelt.push({ id, ...def }) } });

    return gesammelt;
}

describe('Bausteinleiste je Geruest', () => {
    const karte = bausteineVon(registerCardBlocks);
    const brief = bausteineVon(registerBlocks);

    it('bietet auf der Karte die Kartenbausteine an', () => {
        const ids = karte.map((b) => b.id);

        for (const erwartet of ['db-card-title', 'db-card-subtitle', 'db-card-rows', 'db-card-code', 'db-card-image']) {
            assert.ok(ids.includes(erwartet), `${erwartet} fehlt in der Kartenleiste`);
        }
    });

    it('bietet auf der Karte KEINE Rechnungsbausteine an', () => {
        const ids = karte.map((b) => b.id);

        for (const verboten of ['db-line-items', 'db-totals', 'db-page-break']) {
            assert.ok(!ids.includes(verboten), `${verboten} gehoert nicht auf eine Karte`);
        }
    });

    it('laesst die Rechnungsleiste unveraendert', () => {
        const ids = brief.map((b) => b.id);

        for (const erwartet of ['db-line-items', 'db-totals', 'db-page-break', 'db-columns-2']) {
            assert.ok(ids.includes(erwartet), `${erwartet} fehlt in der Rechnungsleiste`);
        }
        // Gegenprobe: die Kartenbausteine sind nicht versehentlich ueberall.
        assert.ok(!ids.includes('db-card-code'), 'Kartenbausteine gehoeren nicht in die Rechnung');
    });

    it('teilt sich die allgemeinen Bausteine', () => {
        for (const gemeinsam of ['db-text', 'db-image', 'db-divider']) {
            assert.ok(karte.map((b) => b.id).includes(gemeinsam), `${gemeinsam} fehlt der Karte`);
            assert.ok(brief.map((b) => b.id).includes(gemeinsam), `${gemeinsam} fehlt dem Brief`);
        }
    });
});

describe('Zeichen der Kartenbausteine', () => {
    it('nimmt ohne Gruppennamen alle Zeilen', () => {
        assert.equal(rowsToken(null), '{{ rows }}');
        assert.equal(rowsToken(''), '{{ rows }}');
    });

    it('waehlt mit Namen genau eine Gruppe', () => {
        assert.equal(rowsToken('workshops'), '{{ rows.workshops }}');
    });

    it('behandelt blosse Leerzeichen als keine Angabe', () => {
        // Sonst entstuende `{{ rows. }}` — ein Zeichen, das die PHP-Seite nie
        // ersetzt und das als Text auf der Karte landet.
        assert.equal(rowsToken('   '), '{{ rows }}');
        assert.equal(codeToken('  '), '{{ code_image }}');
    });

    it('unterscheidet Hauptcode und Zusatzcode', () => {
        assert.equal(codeToken(null), '{{ code_image }}');
        assert.equal(codeToken('workshop-1'), '{{ code_image.workshop-1 }}');
    });

    it('faellt beim Bild auf das Logo zurueck', () => {
        assert.equal(imageToken(''), '{{ image.logo }}');
        assert.equal(imageToken('ticketart'), '{{ image.ticketart }}');
    });
});

describe('Der Code laesst sich nicht wegloeschen', () => {
    it('schuetzt den letzten', () => {
        // Entschieden in #4433: ein Schild ohne Code ist am Einlass wertlos.
        assert.equal(codeRemovability(1), false);
        assert.equal(codeRemovability(0), false);
    });

    it('gibt ab dem zweiten jeden frei', () => {
        assert.equal(codeRemovability(2), true);
        assert.equal(codeRemovability(5), true);
    });
});

describe('Beschriftung der Zonen', () => {
    it('nennt die Bereiche der Karte nach der Karte', () => {
        const html = buildZones({ body: '' }, 'card');

        assert.match(html, /data-gjs-name="Kartenkopf"/);
        assert.match(html, /data-gjs-name="Kartenfuß"/);
        assert.doesNotMatch(html, /Briefkopf/);
    });

    it('laesst den Brief beim Brief', () => {
        const html = buildZones({ body: '' }, 'din5008');

        assert.match(html, /data-gjs-name="Briefkopf"/);
        assert.doesNotMatch(html, /Kartenkopf/);
    });

    it('bleibt ohne Angabe beim Brief', () => {
        assert.match(buildZones({ body: '' }), /data-gjs-name="Briefkopf"/);
    });
});
