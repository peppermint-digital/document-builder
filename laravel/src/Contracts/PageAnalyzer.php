<?php

namespace Peppermint\DocumentBuilder\Contracts;

/**
 * Findet heraus, welche Positionszeile auf welcher Seite gelandet ist.
 *
 * Es gibt diese Frage, weil DomPDF sie beim Bauen nicht beantwortet: Der
 * Umbruch entsteht im Layout, und weder `page_script` noch das Frame-Modell
 * geben preis, welche Tabellenzeile dabei auf welche Seite gerutscht ist. Wer
 * einen Übertrag drucken will, braucht aber genau das — die Summe bis zum
 * Seitenende.
 *
 * Der Weg führt deshalb über das fertige PDF. Weil das ein Werkzeug außerhalb
 * von PHP verlangt, steht hier ein Interface: Der Builder kommt ohne aus (dann
 * eben ohne Übertrag), Tests kommen ohne aus, und wer ein anderes Werkzeug
 * bevorzugt, tauscht die Implementierung.
 */
interface PageAnalyzer
{
    /**
     * Ist die Analyse in dieser Umgebung überhaupt möglich?
     *
     * Wird vor jedem Versuch gefragt. Ein `false` ist kein Fehler, sondern die
     * Ansage, dass der Beleg ohne Übertrag gedruckt wird — so wie bisher.
     */
    public function isAvailable(): bool;

    /**
     * Ordnet Positionsnummern den Seiten zu, auf denen sie stehen.
     *
     * @param  string  $pdf  Die rohen PDF-Bytes.
     * @param  list<string>  $positions  Die Positionsnummern in Druckreihenfolge.
     * @return array<string, int>|null Positionsnummer → Seitenzahl (1-basiert),
     *                                 oder null, wenn die Zuordnung nicht
     *                                 zweifelsfrei gelang. Ein Teilergebnis
     *                                 wäre schlimmer als keines: Es ergäbe
     *                                 einen Übertrag, der nicht stimmt.
     */
    public function pagesByPosition(string $pdf, array $positions): ?array;
}
