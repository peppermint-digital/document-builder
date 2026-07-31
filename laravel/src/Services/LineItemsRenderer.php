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
 * Column widths are always written into a `<colgroup>`. DomPDF's automatic
 * table layout is weak and will happily wrap an amount like "1.137,50 €" onto
 * two lines — explicit widths are not a nicety here, they are the fix.
 */
class LineItemsRenderer
{
    /**
     * The columns a German business document shows by default.
     *
     * @var list<array{key: string, label: string, width: string, align?: string, format?: string}>
     */
    public const DEFAULT_COLUMNS = [
        ['key' => 'position', 'label' => 'Pos.', 'width' => '7%', 'align' => 'right'],
        ['key' => 'description', 'label' => 'Bezeichnung', 'width' => '45%'],
        ['key' => 'quantity', 'label' => 'Menge', 'width' => '10%', 'align' => 'right', 'format' => 'decimal'],
        ['key' => 'unit', 'label' => 'Einheit', 'width' => '10%'],
        ['key' => 'unit_price', 'label' => 'Einzelpreis', 'width' => '14%', 'align' => 'right', 'format' => 'currency'],
        ['key' => 'total', 'label' => 'Gesamt', 'width' => '14%', 'align' => 'right', 'format' => 'currency'],
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
            $head .= '<th class="db-align-'.$this->escape($column['align'] ?? 'left').'">'
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

            $cells .= '<td class="db-align-'.$this->escape($column['align'] ?? 'left').'">'.$content.'</td>';
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
            'currency' => $this->escape(
                number_format((float) $value, 2, $decimal, $thousands)
                .' '.$this->currencySymbol((string) ($options['currency'] ?? 'EUR'))
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
