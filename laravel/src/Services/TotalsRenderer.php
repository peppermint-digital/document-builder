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
     * @param  array{net_label?: string, gross_label?: string, currency?: string, decimal_separator?: string, thousands_separator?: string}  $options
     */
    public function render(Totals $totals, array $options = []): string
    {
        $rows = $this->row(
            (string) ($options['net_label'] ?? 'Zwischensumme'),
            $totals->net,
            $totals,
            $options,
        );

        foreach ($totals->additional as $line) {
            $rows .= $this->row((string) $line['label'], (float) $line['amount'], $totals, $options);
        }

        foreach ($totals->taxes as $tax) {
            $rows .= $this->row((string) $tax['label'], (float) $tax['amount'], $totals, $options);
        }

        $rows .= $this->row(
            (string) ($options['gross_label'] ?? 'Gesamtbetrag'),
            $totals->gross,
            $totals,
            $options,
            'db-total-gross',
        );

        return '<table class="db-totals">'.$rows.'</table>';
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function row(string $label, float $amount, Totals $totals, array $options, string $class = ''): string
    {
        $attribute = $class === '' ? '' : ' class="'.$this->escape($class).'"';

        return '<tr'.$attribute.'>'
            .'<td>'.$this->escape($label).'</td>'
            .'<td class="db-total-amount">'.$this->escape($this->money($amount, $totals, $options)).'</td>'
            .'</tr>';
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
