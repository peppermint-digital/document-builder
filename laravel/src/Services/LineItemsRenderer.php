<?php

namespace Peppermint\DocumentBuilder\Services;

use Peppermint\DocumentBuilder\Data\LineItem;
use Peppermint\DocumentBuilder\DocumentBuilder;

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
     * Rueckfall fuer den Satzspiegel, wenn der Aufrufer keinen nennt.
     *
     * Der echte Wert kommt als `content_width` aus dem Seitenaufbau. Dieser
     * hier ist nur die Zahl, mit der dieser Code begonnen hat — er bleibt
     * stehen, damit ein Aufruf ohne Seitenaufbau sich nicht aendert.
     */
    private const SATZSPIEGEL_MM = 170.0;

    /** Polsterung einer Zelle, links und rechts je 1 mm (siehe Preset-CSS). */
    private const ZELLPOLSTER_MM = 2.0;

    /** Breite eines Zeichens bei 10 pt — gemessen, nicht gerechnet. */
    private const MM_JE_ZEICHEN = 1.96;

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

        // Summen- und Uebertragszeilen bekommen die letzten ZWEI Spalten fuer
        // den Betrag, nicht nur die letzte (Bug #1135).
        //
        // Gemeldet war `Gesamtbetrag13.610,27 €` — Beschriftung und Betrag
        // ohne jeden Abstand. Die Gesamtzeile ist fett gesetzt und passte
        // damit nicht mehr in die 14-%-Spalte, fuer die die Breite gilt;
        // sie lief nach links heraus, genau dorthin, wo die Beschriftung
        // endet. Mit zwei Spalten hat der Betrag das Doppelte und die
        // Beschriftung rueckt eine Spalte nach links — der Abstand, den
        // Volker vermisst hat, entsteht dabei von selbst.
        //
        // Nicht verkleinern wie bei den Positionszeilen: Der Gesamtbetrag
        // ist die Zahl, auf die jeder zuerst schaut. Sie darf nicht die
        // einzige auf dem Beleg sein, die kleiner gesetzt ist.
        $betragSpan = self::betragsSpalten($columnCount);
        $labelSpan = max($columnCount - $betragSpan, 1);
        $html = $lastItemRow;

        foreach ($rows as $row) {
            $class = trim('db-total-row '.$row['class']);

            $html .= '<tr class="'.$this->escape($class).'">'
                .'<td class="db-total-label" colspan="'.$labelSpan.'">'.$this->escape($row['label']).'</td>'
                .'<td class="db-align-right" colspan="'.$betragSpan.'">'.$this->escape($row['amount']).'</td>'
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
     * Ob ein Wert als EIN unteilbares Wort breiter ist als seine Spalte
     * (Bug #929).
     *
     * ## Was passiert ist
     *
     * Auf einer Rechnung stand `Pauschal7.450,00 €` — Einheit und Einzelpreis
     * ohne jeden Abstand aneinander. Nachgemessen bei 170 mm Inhaltsbreite:
     * `Pauschal` belegt 15,7 mm, die Spalte gibt ihm 13,6. Es laeuft also
     * 2,1 mm in die Nachbarspalte, und ob daraus eine Beruehrung wird, haengt
     * nur noch daran, wie lang der Betrag daneben ist.
     *
     * Die Spaltenbreiten taugen nicht als Angriffspunkt: Sie liegen je Vorlage
     * als Datensatz und sind von Hand aenderbar. Was hier gebraucht wird, ist
     * eine Zusage, die fuer JEDE Breite gilt.
     *
     * ## Warum nur einzelne Woerter
     *
     * Mehrwortiger Text bricht um und passt dadurch immer. Nur ein einzelnes
     * langes Wort hat keine Trennstelle und laeuft heraus. Deshalb darf die
     * Bezeichnungsspalte hiervon nie betroffen sein — eine lange Beschreibung
     * soll umbrechen, nicht schrumpfen.
     *
     * ## Zur Schaetzung
     *
     * Die Spaltenbreite steht als Prozentwert. Die Inhaltsbreite reicht der
     * {@see DocumentBuilder} als `content_width`
     * durch — er kennt den Seitenaufbau, dieser Renderer nicht. Ohne Angabe
     * bleibt es bei den 170 mm, mit denen dieser Code begonnen hat.
     *
     * Von der Spalte geht die Polsterung ab: Die Zelle traegt links und rechts
     * je 1 mm, der Text hat also 2 mm weniger als die Spalte breit ist.
     *
     * Rund 1,96 mm je Zeichen bei 10 pt — am Pruefstand gemessen und am
     * 28.10.2026 an einem echten PDF gegengeprueft: `123.437,50 €` belegt
     * 23,2 mm bei 12 Zeichen.
     *
     * Eine Fehlschaetzung ist folgenlos fuer die Richtigkeit: Sie setzt einen
     * Wert kleiner, der auch so gepasst haette, oder laesst einen stehen, der
     * knapp herausragt. Was sie nicht tut, ist Text verlieren.
     *
     * ## Korrektur vom 28.10.2026 (Bug #1135)
     *
     * Hier kamen zwei Fehler zusammen, die zusammen dafuer sorgten, dass
     * Zahlenspalten NIE verkleinert wurden:
     *
     * 1. Der Aufrufer reichte den **Rohwert** herein (`1250.0`), nicht den
     *    gedruckten Text (`1.250,00`). `is_string()` liess ihn durchfallen,
     *    und damit war jede Mengen-, Preis- und Summenspalte ungeprueft.
     * 2. Gerechnet wurde mit 170 mm und ohne Polsterung. Bei den echten
     *    165,9 mm eines DIN-5008-Satzspiegels hat eine 14-%-Spalte 23,2 mm,
     *    nutzbar 21,2 — nicht 23,8. `123.437,50 €` passte in der Rechnung
     *    und auf dem Papier nicht.
     *
     * Gemeldet als `98,75 €123.437,50 €` und `Gesamtbetrag13.610,27 €`.
     *
     * @param  array{key: string, label: string, width: string, align?: string, format?: string}  $column
     * @param  array<string, mixed>  $options
     */
    private function laeuftUeber(mixed $wert, array $column, array $options = []): bool
    {
        if (! is_string($wert) || trim($wert) === '' || str_contains(trim($wert), ' ')) {
            return false;
        }

        $platz = $this->nutzbareBreite($column, $options);

        if ($platz <= 0.0) {
            return false;
        }

        return mb_strlen(trim($wert)) * self::MM_JE_ZEICHEN > $platz;
    }

    /**
     * Was in dieser Spalte an Text Platz hat, in Millimetern — Spaltenanteil
     * des Satzspiegels, abzueglich der Polsterung der Zelle.
     *
     * @param  array{key: string, label: string, width: string, align?: string, format?: string}  $column
     * @param  array<string, mixed>  $options
     */
    private function nutzbareBreite(array $column, array $options): float
    {
        $anteil = (float) rtrim($column['width'], '% ');

        if ($anteil <= 0.0) {
            return 0.0;
        }

        $satzspiegel = (float) ($options['content_width'] ?? self::SATZSPIEGEL_MM);

        return ($anteil / 100) * $satzspiegel - self::ZELLPOLSTER_MM;
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
     * Wie viele Spalten der Betrag einer Summen- oder Uebertragszeile bekommt.
     *
     * Zwei, sobald die Tabelle genug Spalten hat — siehe die Begruendung in
     * {@see self::closingGroup()}. Bei einer schmalen Tabelle (Pos. und
     * Bezeichnung und sonst nichts) bleibt es bei einer, sonst stuende die
     * Beschriftung nirgends mehr.
     */
    private static function betragsSpalten(int $columnCount): int
    {
        return $columnCount >= 3 ? 2 : 1;
    }

    /**
     * Eine Übertragszeile — dieselbe Breite wie der Betrag im Summenblock,
     * damit beide rechtsbuendig untereinander stehen.
     *
     * @param  list<array{key: string, label: string, width: string, align?: string, format?: string}>  $columns
     * @param  array<string, mixed>  $options
     */
    private function carryRow(string $label, float $amount, array $columns, array $options, string $class): string
    {
        $spalten = count($columns);
        $betragSpan = self::betragsSpalten($spalten);

        // Die Uebertragszeile ist fett gesetzt wie die Gesamtzeile und hatte
        // damit dasselbe Problem (Bug #1135). Eine feste `width` steht hier
        // nicht mehr: Bei zwei Spalten waere sie falsch, und die rechte Kante
        // — auf die es ankommt — legt ohnehin die Tabelle fest.
        return '<tr class="db-carry '.$this->escape($class).'">'
            .'<td class="db-align-right" colspan="'.max($spalten - $betragSpan, 1).'">'.$this->escape($label).'</td>'
            .'<td class="db-align-right" colspan="'.$betragSpan.'">'
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

            // Gemessen wird, was GEDRUCKT wird — nicht der Rohwert (Bug #1135).
            // `1250.0` ist keine Zeichenkette und fiel in der Pruefung durch;
            // auf dem Papier steht aber `1.250,00` und belegt 15,7 mm.
            $gedruckt = $content;

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
            $klassen = 'db-align-'.$this->escape($column['align'] ?? 'left');

            if ($this->laeuftUeber($gedruckt, $column, $options)) {
                $klassen .= ' db-eng';
            }

            $cells .= '<td class="'.$klassen.'"'
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
