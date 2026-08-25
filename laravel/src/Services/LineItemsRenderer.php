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

        $rows = array_map(fn (LineItem $item): string => $this->renderRow($item, $columns, $options), $items);

        // Die letzte Positionszeile wandert in die Schlussgruppe, damit sie den
        // Summenblock nicht allein auf einer leeren Seite zurücklässt.
        $tail = $totalRows !== [] && $rows !== [] ? array_pop($rows) : '';

        $body = implode('', $rows);

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
     * @param  list<array{key: string, label: string, width: string, align?: string, format?: string}>  $columns
     * @param  array<string, mixed>  $options
     */
    private function renderRow(LineItem $item, array $columns, array $options): string
    {
        $cells = '';

        foreach ($columns as $column) {
            $content = $this->formatValue($item->value($column['key']), $column['format'] ?? 'text', $options);

            // The note rides along under the description rather than claiming a
            // column of its own — that is where a reader expects it.
            if ($column['key'] === 'description' && $item->note !== null && $item->note !== '') {
                $content .= '<span class="db-note">'.$this->escape($item->note).'</span>';
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

        return '<tr>'.$cells.'</tr>';
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
