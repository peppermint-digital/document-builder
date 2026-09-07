<?php

namespace Peppermint\DocumentBuilder\Services;

use Peppermint\DocumentBuilder\Contracts\PageAnalyzer;

/**
 * Liest die Seitenaufteilung mit `pdftotext` aus dem fertigen PDF.
 *
 * `pdftotext` gehört zu poppler-utils und liegt auf den meisten Servern
 * ohnehin. Fehlt es, meldet {@see self::isAvailable()} das, und der Beleg
 * entsteht ohne Übertrag — die Funktion ist eine Zugabe, kein Fundament.
 *
 * Warum überhaupt der Umweg über das fertige PDF: siehe {@see PageAnalyzer}.
 */
class PdftotextPageAnalyzer implements PageAnalyzer
{
    private ?bool $available = null;

    public function __construct(
        private readonly string $binary = 'pdftotext',
        private readonly int $timeoutSeconds = 15,
    ) {}

    public function isAvailable(): bool
    {
        // Einmal je Instanz: Der Aufruf kostet einen Prozess, und die Antwort
        // ändert sich zur Laufzeit nicht.
        return $this->available ??= $this->probe();
    }

    public function pagesByPosition(string $pdf, array $positions): ?array
    {
        if ($positions === [] || ! $this->isAvailable()) {
            return null;
        }

        $datei = tempnam(sys_get_temp_dir(), 'db-carry-').'.pdf';

        if ($datei === false || file_put_contents($datei, $pdf) === false) {
            return null;
        }

        try {
            $seiten = $this->seitentexte($datei);
        } finally {
            @unlink($datei);
        }

        if ($seiten === null) {
            return null;
        }

        $zuordnung = [];

        foreach ($positions as $position) {
            $seite = $this->seiteFuer((string) $position, $seiten);

            // Eine einzige unklare Zeile macht den ganzen Übertrag unbrauchbar:
            // Fehlt sie, ist die Summe bis zum Seitenende falsch — und ein
            // falscher Übertrag ist schlimmer als keiner.
            if ($seite === null) {
                return null;
            }

            $zuordnung[(string) $position] = $seite;
        }

        return $zuordnung;
    }

    /**
     * Der Text je Seite, 1-basiert.
     *
     * Ein einziger Aufruf für das ganze Dokument: `pdftotext` trennt die Seiten
     * mit einem Form Feed, das lässt sich hier aufteilen. Der naheliegende Weg
     * — je Seite ein Aufruf mit `-f N -l N` — kostet einen Prozess pro Seite
     * und braucht vorher die Seitenzahl, die selbst wieder ein Aufruf ist.
     *
     * @return array<int, string>|null
     */
    private function seitentexte(string $datei): ?array
    {
        $text = $this->run([$this->binary, '-layout', $datei, '-']);

        if ($text === null) {
            return null;
        }

        $seiten = explode("\f", $text);

        // Hinter der LETZTEN Seite steht ebenfalls ein Form Feed. Ohne diesen
        // Schnitt entstünde eine leere Geisterseite — und mit ihr eine
        // Seitenzahl, die um eins zu hoch ist.
        while ($seiten !== [] && trim((string) end($seiten)) === '') {
            array_pop($seiten);
        }

        if ($seiten === []) {
            return null;
        }

        // 1-basiert, wie Seitenzahlen gelesen werden.
        return array_combine(range(1, count($seiten)), $seiten);
    }

    /**
     * @param  array<int, string>  $seiten
     */
    private function seiteFuer(string $position, array $seiten): ?int
    {
        $gefunden = null;

        foreach ($seiten as $nummer => $text) {
            // Die Positionsnummer steht am Zeilenanfang in der ersten Spalte.
            // Ohne diese Verankerung würde „1" auch in „100,00 €" oder in einer
            // Artikelnummer treffen.
            if (preg_match('/^\s*'.preg_quote($position, '/').'\s+\S/m', $text) !== 1) {
                continue;
            }

            // Zweimal gefunden heisst: Das Muster ist nicht eindeutig. Dann
            // lieber gar kein Übertrag als ein falscher.
            if ($gefunden !== null) {
                return null;
            }

            $gefunden = $nummer;
        }

        return $gefunden;
    }

    private function probe(): bool
    {
        return $this->run([$this->binary, '-v']) !== null;
    }

    /**
     * @param  list<string>  $command
     */
    private function run(array $command): ?string
    {
        $prozess = @proc_open(
            $command,
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );

        if (! is_resource($prozess)) {
            return null;
        }

        // `-v` schreibt seine Ausgabe nach stderr, die Textausgabe geht nach
        // stdout — beides einsammeln und zusammenlegen.
        $ausgabe = (string) stream_get_contents($pipes[1]);
        $fehler = (string) stream_get_contents($pipes[2]);

        foreach ($pipes as $pipe) {
            fclose($pipe);
        }

        $code = proc_close($prozess);

        if ($code !== 0) {
            return null;
        }

        return $ausgabe !== '' ? $ausgabe : $fehler;
    }
}
