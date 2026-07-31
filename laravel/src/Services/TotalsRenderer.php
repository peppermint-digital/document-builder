<?php

namespace Peppermint\DocumentBuilder\Services;

use Peppermint\DocumentBuilder\Data\Totals;

/**
 * Lays out the summary block below the line items.
 *
 * Calculates nothing: VAT rules, rounding and reverse charge belong to the
 * host. This only decides what the block looks like.
 */
class TotalsRenderer
{
    /**
     * The summary lines as label/amount pairs, already formatted.
     *
     * Exposed separately so the line-item table can absorb them as trailing
     * rows — see {@see LineItemsRenderer::render()} for why that matters.
     *
     * @param  array{net_label?: string, gross_label?: string, currency?: string, decimal_separator?: string, thousands_separator?: string}  $options
     * @return list<array{label: string, amount: string, class: string}>
     */
    public function rows(Totals $totals, array $options = []): array
    {
        $rows = [[
            'label' => (string) ($options['net_label'] ?? 'Zwischensumme'),
            'amount' => $this->money($totals->net, $totals, $options),
            'class' => '',
        ]];

        foreach ($totals->additional as $line) {
            $rows[] = [
                'label' => (string) $line['label'],
                'amount' => $this->money((float) $line['amount'], $totals, $options),
                'class' => '',
            ];
        }

        foreach ($totals->taxes as $tax) {
            $rows[] = [
                'label' => (string) $tax['label'],
                'amount' => $this->money((float) $tax['amount'], $totals, $options),
                'class' => '',
            ];
        }

        $rows[] = [
            'label' => (string) ($options['gross_label'] ?? 'Gesamtbetrag'),
            'amount' => $this->money($totals->gross, $totals, $options),
            'class' => 'db-total-gross',
        ];

        return $rows;
    }

    /**
     * The standalone summary table, for templates that place the block away
     * from the line items.
     *
     * @param  array{net_label?: string, gross_label?: string, currency?: string, decimal_separator?: string, thousands_separator?: string}  $options
     */
    public function render(Totals $totals, array $options = []): string
    {
        $html = '';

        foreach ($this->rows($totals, $options) as $row) {
            $attribute = $row['class'] === '' ? '' : ' class="'.$this->escape($row['class']).'"';

            $html .= '<tr'.$attribute.'>'
                .'<td>'.$this->escape($row['label']).'</td>'
                .'<td class="db-total-amount">'.$this->escape($row['amount']).'</td>'
                .'</tr>';
        }

        return '<table class="db-totals">'.$html.'</table>';
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function money(float $amount, Totals $totals, array $options): string
    {
        $decimal = (string) ($options['decimal_separator'] ?? ',');
        $thousands = (string) ($options['thousands_separator'] ?? '.');
        $currency = strtoupper((string) ($options['currency'] ?? $totals->currency));

        $symbol = match ($currency) {
            'EUR' => '€',
            'USD' => '$',
            'GBP' => '£',
            default => $currency,
        };

        return number_format($amount, 2, $decimal, $thousands).' '.$symbol;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
