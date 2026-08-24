import { CARD_DEFAULT, DIN_5008 } from './defaults';
import { canvasCss, cardCanvasCss, cardSkeletonHtml, skeletonHtml } from './theme';
import type { CardSetup, PageSetup, PresetName, SkeletonPreview } from './types';

/**
 * Das Geruest, gegen das eine Vorlage gebaut wird — die Editor-Seite von
 * `Contracts\DocumentPreset`.
 *
 * Die Trennung ist dieselbe wie in PHP und aus demselben Grund: dem Preset
 * gehoert die Geometrie, der Vorlage gehoert der Inhalt. Ein Baukasten, der
 * beides vermischt, erlaubt eine Rechnung, deren Anschriftfeld nicht mehr ins
 * Fensterkuvert passt — und eine Karte, die ueber die Schnittkante steht.
 *
 * Vor den Karten war diese Trennung im Code nicht sichtbar: es gab nur ein
 * Geruest, und seine Annahmen standen verstreut in `editor.ts` und `theme.ts`.
 * Sie hier zu buendeln ist der ganze Umbau; ein zweites Geruest ist danach ein
 * Objekt, kein zweiter Editor.
 */
export interface EditorPreset {
    name: PresetName;
    /** CSS der Leinwand — sie muss mit dem Renderer uebereinstimmen. */
    canvasCss(preview: SkeletonPreview): string;
    /** Dekoration, die dem Geruest gehoert: sichtbar, nicht waehlbar, nicht speicherbar. */
    skeletonHtml(preview: SkeletonPreview): string;
    /** Womit der Mittelteil einer frischen Vorlage beginnt. */
    starterBody(): string;
}

/** Womit der Mittelteil einer frischen Rechnung beginnt. */
const STARTER_BODY_DIN =
    '<p>Sehr geehrte Damen und Herren,</p>' +
    '<p>vielen Dank für Ihre Anfrage. Gern unterbreiten wir Ihnen folgendes Angebot.</p>' +
    '<table data-db-block="line-items" class="db-line-items"></table>' +
    '<table data-db-block="totals" class="db-totals"></table>' +
    '<p>Wir freuen uns auf Ihre Rückmeldung.</p>';

/**
 * Womit eine frische Karte beginnt.
 *
 * Titel und Untertitel, sonst nichts. Die Zeilengruppen kommen aus den Daten
 * und werden vom Nutzer gesetzt — sie hier vorzubelegen hiesse, eine
 * Veranstaltung zu erfinden, die es nicht gibt.
 */
const STARTER_BODY_CARD =
    '<p class="db-card-title">{{ title }}</p>' +
    '<p class="db-card-subtitle">{{ subtitle }}</p>';

/** Das Blatt-Geruest nach DIN 5008 — die Fassung, die es vor den Karten allein gab. */
export function din5008Preset(page: PageSetup = DIN_5008): EditorPreset {
    return {
        name: 'din5008',
        canvasCss: (preview) => canvasCss(page, preview),
        skeletonHtml: (preview) => skeletonHtml(preview),
        starterBody: () => STARTER_BODY_DIN,
    };
}

/** Das Karten-Geruest — Namensschild, Ticket, und was sonst auf eine Karte passt. */
export function cardPreset(card: CardSetup = CARD_DEFAULT): EditorPreset {
    return {
        name: 'card',
        canvasCss: (preview) => cardCanvasCss(card, preview),
        skeletonHtml: (preview) => cardSkeletonHtml(preview),
        starterBody: () => STARTER_BODY_CARD,
    };
}

/**
 * Waehlt das Geruest aus den Editor-Optionen.
 *
 * `din5008` ist die Vorgabe und bleibt es: wer die Option nicht setzt, bekommt
 * genau das Verhalten von vorher.
 */
export function resolvePreset(
    name: PresetName | undefined,
    page: PageSetup,
    card: Partial<CardSetup> | undefined,
): EditorPreset {
    return name === 'card' ? cardPreset({ ...CARD_DEFAULT, ...card }) : din5008Preset(page);
}
