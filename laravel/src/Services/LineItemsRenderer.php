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
     * @param  list<LineItem>  $items
     * @param  array{columns?: list<array{key: string, label: string, width: string, align?: string, format?: string}>, currency?: string, decimal_separator?: string, thousands_separator?: string, empty_text?: string}  $options
     */
    public function render(array $items, array $options = []): string
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

        $body = '';

        foreach ($items as $item) {
            $body .= $this->renderRow($item, $columns, $options);
        }

        if ($body === '') {
            $body = '<tr><td class="db-empty" colspan="'.count($columns).'">'
                .$this->escape($options['empty_text'] ?? '—')
                .'</td></tr>';
        }

        return '<table class="db-line-items">'
            .'<colgroup>'.$colgroup.'</colgroup>'
            // <thead> is what makes the header repeat after a page break.
            // Verified against DomPDF; do not fold these rows into <tbody>.
            .'<thead><tr>'.$head.'</tr></thead>'
            .'<tbody>'.$body.'</tbody>'
            .'</table>';
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
