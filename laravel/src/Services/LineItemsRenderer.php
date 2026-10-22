<?php

namespace Peppermint\DocumentBuilder\Services;

use Peppermint\DocumentBuilder\Data\LineItem;

/**
 * Lays out the line-item table.
 *
 * This exists because a table of unknown length is not a placeholder. A
 * template can say where the table goes and which columns it has; how many
 * rows it grows to is decided at print time.
 *
 * Column widths are always written into a `<colgroup>` und gelten wegen
 * `table-layout: fixed` im Preset verbindlich. Ohne das sind sie fuer DomPDF
 * nur ein Vorschlag: Es misst die Inhalte nach und verteilt neu — und weil bei
 * genau einer Position der Rumpf nur noch eine Zelle ueber alle Spalten
 * enthaelt, richtete sich der Kopf dann nach den Ueberschriften und die Zeile
 * nach ihrem Inhalt. Die Spalten standen sichtbar versetzt.
 *
 * Die Betragsspalten sind bewusst breit: Sie tragen `white-space: nowrap`, und
 * was dort nicht hineinpasst, wird nicht umgebrochen, sondern laeuft heraus.
 */
class LineItemsRenderer
{
    /**
     * The columns a German business document shows by default.
     *
     * @var list<array{key: string, label: string, width: string, align?: string, format?: string}>
     */
    public const DEFAULT_COLUMNS = [
        ['key' => 'position', 'label' => 'Pos.', 'width' => '6%', 'align' => 'right'],
        ['key' => 'description', 'label' => 'Bezeichnung', 'width' => '46%'],
        ['key' => 'quantity', 'label' => 'Menge', 'width' => '7%', 'align' => 'right', 'format' => 'decimal'],
        ['key' => 'unit', 'label' => 'Einheit', 'width' => '7%'],
        ['key' => 'unit_price', 'label' => 'Einzelpreis', 'width' => '17%', 'align' => 'right', 'format' => 'currency'],
        ['key' => 'total', 'label' => 'Gesamt', 'width' => '17%', 'align' => 'right', 'format' => 'currency'],
    ];

    /**
     * Renders the table, optionally absorbing the summary as trailing rows.
     *
     * Absorbing is not cosmetic. As a separate block the summary carries
     * `page-break-inside: avoid`, so when it does not fit in the remaining space
     * it jumps to the next page *whole* — and nothing pulls a line item along,
     * leaving the summary alone on an otherwise empty last sheet. In ten
     * measured table lengths that happened three times. As table rows it flows
     * with the rows before it and the case disappears.
     *
     * @param  list<LineItem>  $items
     * @param  array{columns?: list<array{key: string, label: string, width: string, align?: string, format?: string}>, currency?: string, decimal_separator?: string, thousands_separator?: string, empty_text?: string}  $options
     * @param  list<array{label: string, amount: string, class: string}>  $totalRows
     */
    public function render(array $items, array $options = [], array $totalRows = []): string
    {
        $columns = $options['columns'] ?? self::DEFAULT_COLUMNS;

        $colgroup = '';
        $head = '';

        foreach ($columns as $column) {
            $colgroup .= '<col style="width: '.$this->escape($column['width']).'">';

            // Die Breite steht ZUSAETZLICH an der Kopfzelle. Bei
            // `table-layout: fixed` bestimmt die erste Zeile die Spalten, und
            // DomPDF zieht dafuer die Zellen heran — die Angaben im <colgroup>
            // allein liess es unbeachtet und verteilte nach der Breite der
            // Ueberschriften. Die Bezeichnung bekam dadurch den Rest statt
            // ihres Anteils und brach auf fuenf Zeilen um. Gemessen, nicht
            // vermutet: mit Attribut am <col> aenderte sich nichts, mit der
            // Angabe hier sofort.
            $head .= '<th class="db-align-'.$this->escape($column['align'] ?? 'left').'"'
                .' style="width: '.$this->escape($column['width']).'">'
                .$this->escape($column['label'])
                .'</th>';
        }

        $rows = [];

        // Der Übertrag (#5039): Wo eine Seite endet, steht die Summe bis dorthin
        // — und oben auf der nächsten Seite noch einmal. Die Aufteilung kommt
        // von aussen, weil nur das fertige PDF sie kennt; ohne sie bleibt die
        // Schleife das, was sie vorher war.
        $umbruchNach = $options['page_breaks'] ?? [];
        $laufend = 0.0;
        $letzterIndex = count($items) - 1;

        foreach (array_values($items) as $index => $item) {
            // Eine Position kann MEHRERE Zeilen belegen (lange Notiz, siehe
            // `renderRow()`). Je Position bleibt es trotzdem EIN Eintrag —
            // sonst verschoeben sich die Indizes, auf die `page_breaks` zeigt.
            $rows[] = $this->renderRow($item, $columns, $options);
            $laufend += (float) ($item->total ?? 0);

            // Nach der letzten Zeile nie — dort folgt der Summenblock.
            if ($index === $letzterIndex || ! in_array($index + 1, $umbruchNach, strict: true)) {
                continue;
            }

            $rows[] = [$this->carryRow('Übertrag', $laufend, $columns, $options, 'db-carry-end')];
            $rows[] = ['<tr class="db-page-break-row"><td colspan="'.count($columns).'"></td></tr>'];
            $rows[] = [$this->carryRow('Übertrag', $laufend, $columns, $options, 'db-carry-start')];
        }

        // Die letzte Positionszeile wandert in die Schlussgruppe, damit sie den
        // Summenblock nicht allein auf einer leeren Seite zurücklässt.
        //
        // Belegt die letzte Position mehrere Zeilen, wandert NUR ihre letzte
        // mit (Bug #948). Den ganzen Block mitzunehmen hiesse, eine Position
        // mit langer Notiz samt Summe in einen unteilbaren Block zu stecken —
        // der passt dann nirgends mehr hin und laesst die Seite davor leer.
        // Bei kurzer Notiz besteht der Block aus genau einer Zeile; dort ist
        // das unveraendert dasselbe wie vorher.
        $tail = '';

        if ($totalRows !== [] && $rows !== []) {
            $letzte = array_pop($rows);
            $tail = (string) array_pop($letzte);

            if ($letzte !== []) {
                $rows[] = $letzte;
            }
        }

        $body = '';

        foreach ($rows as $gruppe) {
            $body .= implode('', $gruppe);
        }

        if ($body === '' && $tail === '') {
            $body = '<tr><td class="db-empty" colspan="'.count($columns).'">'
                .$this->escape($options['empty_text'] ?? '—')
                .'</td></tr>';
        }

        return '<table class="db-line-items">'
            .'<colgroup>'.$colgroup.'</colgroup>'
            // <thead> is what makes the header repeat after a page break.
            // Verified against DomPDF; do not fold these rows into <tbody>.
            .'<thead><tr>'.$head.'</tr></thead>'
            .($body === '' ? '' : '<tbody>'.$body.'</tbody>')
            .$this->closingGroup($tail, $totalRows, count($columns), $colgroup)
            .'</table>';
    }

    /**
     * The last item row and the summary rows as one unbreakable group.
     *
     * Absorbing the summary into the table is not enough on its own: rows flow
     * individually, so the summary rows alone still move to a fresh page when
     * they do not fit. Keeping them together with the row above means the group
     * either fits or moves as a whole — and a moved group carries a line item
     * with it, so the last sheet is never just a summary.
     *
     * @param  list<array{label: string, amount: string, class: string}>  $rows
     */
    private function closingGroup(string $lastItemRow, array $rows, int $columnCount, string $colgroup): string
    {
        if ($rows === []) {
            return $lastItemRow === '' ? '' : '<tbody>'.$lastItemRow.'</tbody>';
        }

        $labelSpan = max($columnCount - 1, 1);
        $html = $lastItemRow;

        foreach ($rows as $row) {
            $class = trim('db-total-row '.$row['class']);

            $html .= '<tr class="'.$this->escape($class).'">'
                .'<td class="db-total-label" colspan="'.$labelSpan.'">'.$this->escape($row['label']).'</td>'
                .'<td class="db-align-right">'.$this->escape($row['amount']).'</td>'
                .'</tr>';
        }

        // Als verschachtelte Tabelle in einer einzigen Zelle. DomPDF beachtet
        // `page-break-inside` weder auf <tbody> noch auf <tr> — beides gemessen
        // und verworfen —, wohl aber auf einer Tabelle. Die äußere Zeile wandert
        // damit als Ganzes und nimmt die Positionszeile mit.
        return '<tbody class="db-totals-rows"><tr><td class="db-closing-cell" colspan="'.$columnCount.'">'
            .'<table class="db-closing"><colgroup>'.$colgroup.'</colgroup><tbody>'.$html.'</tbody></table>'
            .'</td></tr></tbody>';
    }

    /**
     * Eine lange Notiz als EIGENE Zeilen — oder nichts (Bug #948).
     *
     * ## Warum das nötig ist
     *
     * Die Notiz ritt bisher immer in der Bezeichnungszelle ihrer Position mit.
     * Bei einem umfangreichen Text wurde die Zeile dadurch sehr hoch — im
     * gemeldeten Fall rund 160 mm —, und **DomPDF kann eine Tabellenzeile
     * nicht über eine Seitengrenze teilen**. Eine Zeile, die nicht mehr in den
     * Rest der Seite passt, springt als Ganzes auf die nächste. Zurück blieb
     * der wiederholte Tabellenkopf über einer leeren Seite.
     *
     * Gemessen an AB-2026-0038 und lokal nachgebaut: mit langer Notiz trug
     * Seite 1 null Positionen, bei zwei und drei Positionen ebenso. In eigenen
     * Zeilen füllt sich Seite 1, und der Umbruch fällt zwischen zwei Absätze.
     *
     * ## Warum nur bei LANGEN Notizen
     *
     * Eigene Zeilen sind nicht gratis: Sie verändern den Satz. Eine kurze
     * Notiz — der Regelfall, etwa „135 g/m² Bilderdruck matt" — hat das
     * Problem nicht und bleibt deshalb unangetastet in ihrer Zelle. So ändert
     * sich das Aussehen nur bei Belegen, die heute ohnehin falsch umbrechen.
     *
     * ## Zur Schwelle
     *
     * `$grenze` ist eine **Schätzung**, keine Messung — die Höhe kennt erst
     * der Renderer. Sie ist aus der Geometrie gewählt: Eine Notizzeile ist bei
     * 8 pt gut 3,2 mm hoch, der nutzbare Textbereich einer Folgeseite rund
     * 200 mm. Ab etwa 20 Zeilen belegt die Notiz damit mehr als ein Drittel
     * der Seite, und ein Sprung wird wahrscheinlich.
     *
     * Falsch geschätzt ist hier folgenlos für die Richtigkeit: Beide
     * Darstellungen sind gültig, die eine bricht nur besser um. Eine zu hoch
     * gegriffene Schwelle lässt einen Beleg umbrechen wie bisher, eine zu
     * niedrige setzt eine Notiz in eigene Zeilen, die auch in der Zelle
     * gepasst hätte. Wer nachsteuern will, setzt `note_rows_min_lines`.
     *
     * @param  list<array{key: string, label: string, width: string, align?: string, format?: string}>  $columns
     * @param  array<string, mixed>  $options
     * @return list<string>
     */
    private function notizAlsZeilen(LineItem $item, array $columns, array $options): array
    {
        if ($item->note === null || $item->note === '') {
            return [];
        }

        $grenze = (int) ($options['note_rows_min_lines'] ?? 20);

        if ($grenze <= 0) {
            return [];
        }

        $absaetze = preg_split('/\R/', $item->note) ?: [];

        if ($this->geschaetzteZeilen($absaetze, $columns) < $grenze) {
            return [];
        }

        $zeilen = [];
        $letzter = count($absaetze) - 1;

        foreach ($absaetze as $i => $absatz) {
            $zellen = '';

            foreach ($columns as $column) {
                $inhalt = $column['key'] === 'description'
                    ? '<span class="db-note">'.($absatz === '' ? '&nbsp;' : $this->escape($absatz)).'</span>'
                    : '';

                $zellen .= '<td class="db-align-'.$this->escape($column['align'] ?? 'left').'"'
                    .' style="width: '.$this->escape($column['width']).'">'
                    .$inhalt
                    .'</td>';
            }

            // Nur die LETZTE Notizzeile schliesst die Position ab und traegt
            // damit die Trennlinie. Alles darueber bleibt offen, sonst zoege
            // sich eine Linie durch jeden Absatz.
            $klasse = 'db-note-row'.($i === $letzter ? '' : ' db-row-open');

            $zeilen[] = '<tr class="'.$klasse.'">'.$zellen.'</tr>';
        }

        return $zeilen;
    }

    /**
     * Wie viele gedruckte Zeilen die Absätze in der Bezeichnungsspalte etwa
     * belegen.
     *
     * Der Umbruch je Absatz wird aus der Spaltenbreite geschätzt: Bei 8 pt
     * gehen auf 1 % Seitenbreite ungefähr ein Zeichen — bei 46 % also rund 46
     * Zeichen je Zeile. Ein leerer Absatz belegt trotzdem eine Zeile.
     *
     * @param  list<string>  $absaetze
     * @param  list<array{key: string, label: string, width: string, align?: string, format?: string}>  $columns
     */
    private function geschaetzteZeilen(array $absaetze, array $columns): int
    {
        $breite = 46.0;

        foreach ($columns as $column) {
            if ($column['key'] === 'description') {
                $breite = max((float) rtrim($column['width'], '% '), 10.0);
                break;
            }
        }

        $zeichenJeZeile = max((int) round($breite), 10);
        $zeilen = 0;

        foreach ($absaetze as $absatz) {
            $zeilen += max(1, (int) ceil(mb_strlen($absatz) / $zeichenJeZeile));
        }

        return $zeilen;
    }

    /**
     * Eine Übertragszeile — dieselbe Spaltenbreite wie die Positionen, damit
     * der Betrag unter der Gesamtspalte steht und nicht daneben.
     *
     * @param  list<array{key: string, label: string, width: string, align?: string, format?: string}>  $columns
     * @param  array<string, mixed>  $options
     */
    private function carryRow(string $label, float $amount, array $columns, array $options, string $class): string
    {
        $spalten = count($columns);
        $breite = $columns[$spalten - 1]['width'] ?? '17%';

        return '<tr class="db-carry '.$this->escape($class).'">'
            .'<td class="db-align-right" colspan="'.max($spalten - 1, 1).'">'.$this->escape($label).'</td>'
            .'<td class="db-align-right" style="width: '.$this->escape($breite).'">'
            .$this->formatValue($amount, 'currency', $options)
            .'</td></tr>';
    }

    /**
     * @param  list<array{key: string, label: string, width: string, align?: string, format?: string}>  $columns
     * @param  array<string, mixed>  $options
     */
    private function renderRow(LineItem $item, array $columns, array $options): array
    {
        $notizzeilen = $this->notizAlsZeilen($item, $columns, $options);
        $cells = '';

        foreach ($columns as $column) {
            $content = $this->formatValue($item->value($column['key']), $column['format'] ?? 'text', $options);

            // The note rides along under the description rather than claiming a
            // column of its own — that is where a reader expects it.
            //
            // Zeilenumbrueche bleiben Zeilenumbrueche. Die Notiz traegt in der
            // Praxis eine Aufzaehlung — Artikelnummer, Farbe, Groessenaufteilung
            // je Zeile —, und ohne diesen Schritt lief sie zu einem Fliesstext
            // zusammen, in dem „100 x Groesse S" und „100 x Groesse M"
            // nebeneinander standen. Auf dem Papier war das nicht mehr
            // auseinanderzuhalten.
            if ($column['key'] === 'description'
                && $notizzeilen === []
                && $item->note !== null
                && $item->note !== '') {
                $content .= '<span class="db-note">'.nl2br($this->escape($item->note), false).'</span>';
            }

            // Die Breite auch hier: Diese Zeile ist bei genau einer Position
            // die ERSTE Zeile der verschachtelten Schlusstabelle, und bei
            // `table-layout: fixed` bestimmt die erste Zeile die Spalten. Ohne
            // die Angabe richtete sich der Kopf nach den Prozentwerten und die
            // Zeile nach ihrem Inhalt — sichtbar versetzt.
            $cells .= '<td class="db-align-'.$this->escape($column['align'] ?? 'left').'"'
                .' style="width: '.$this->escape($column['width']).'">'
                .$content
                .'</td>';
        }

        // Steht die Notiz in eigenen Zeilen, traegt die Positionszeile keine
        // Trennlinie — die gehoert unter die ganze Position, nicht zwischen
        // Bezeichnung und Notiz.
        $erste = '<tr'.($notizzeilen === [] ? '' : ' class="db-row-open"').'>'.$cells.'</tr>';

        return array_merge([$erste], $notizzeilen);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function formatValue(string|float|int|bool|null $value, string $format, array $options): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $decimal = (string) ($options['decimal_separator'] ?? ',');
        $thousands = (string) ($options['thousands_separator'] ?? '.');

        return match ($format) {
            'decimal' => $this->escape(number_format((float) $value, 2, $decimal, $thousands)),
            'integer' => $this->escape(number_format((float) $value, 0, $decimal, $thousands)),
            // Geschuetztes Leerzeichen zwischen Zahl und Zeichen: Ein normales
            // erlaubt den Umbruch, und „11.375,00" mit einem „€" auf der
            // naechsten Zeile liest sich wie ein anderer Betrag. Das CSS im
            // Preset setzt zusaetzlich `white-space: nowrap` — beides, weil das
            // eine die Trennstelle beseitigt und das andere die Zelle
            // zusammenhaelt, auch wenn die Spalte zu schmal geraten ist.
            'currency' => $this->escape(
                number_format((float) $value, 2, $decimal, $thousands)
                ."\u{00A0}".$this->currencySymbol((string) ($options['currency'] ?? 'EUR'))
            ),
            default => $this->escape((string) $value),
        };
    }

    private function currencySymbol(string $currency): string
    {
        return match (strtoupper($currency)) {
            'EUR' => '€',
            'USD' => '$',
            'GBP' => '£',
            'CHF' => 'CHF',
            default => strtoupper($currency),
        };
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
