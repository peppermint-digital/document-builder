<?php

namespace Peppermint\DocumentBuilder\Presets;

use Peppermint\DocumentBuilder\Contracts\DocumentPreset;
use Peppermint\DocumentBuilder\Data\DocumentData;
use Peppermint\DocumentBuilder\Data\PageSetup;

/**
 * German business letter skeleton, DIN 5008 form B.
 *
 * All offsets below are the ones the standard specifies, measured from the
 * paper edge, and are converted to content-box offsets through
 * {@see PageSetup::fromPaperTop()}. That conversion is the whole reason this
 * skeleton is package-owned: absolutely positioned elements sit outside the
 * flow, so an offset applied against the wrong origin prints the subject line
 * straight through the address field.
 *
 * The CSS is deliberately flat — no `@media print`, no flexbox, no grid, and
 * explicit column widths — because that is what DomPDF actually renders.
 */
class Din5008Preset implements DocumentPreset
{
    /** Top edge of the address field, from the paper edge. */
    private const ADDRESS_TOP = 45.0;

    /** Left edge of the address field, from the paper edge. */
    private const ADDRESS_LEFT = 20.0;

    private const ADDRESS_WIDTH = 85.0;

    /** Supplementary zone (postal endorsements) above the address itself. */
    private const ADDRESS_ZUSATZ_HEIGHT = 17.7;

    private const ADDRESS_ZONE_HEIGHT = 27.3;

    /** Left edge of the information block on the right-hand side. */
    private const INFO_LEFT = 125.0;

    private const INFO_WIDTH = 75.0;

    /** Top edge of the subject line. */
    private const SUBJECT_TOP = 98.4;

    private const FOLD_MARK_ONE = 87.0;

    private const FOLD_MARK_TWO = 192.0;

    private const HOLE_MARK = 148.5;

    public function name(): string
    {
        return 'din5008';
    }

    public function css(PageSetup $page, array $options = []): string
    {
        $font = $this->escape((string) ($options['font_family'] ?? 'DejaVu Sans'));
        $size = (float) ($options['font_size'] ?? 10);
        $lineHeight = (float) ($options['line_height'] ?? 1.35);
        $color = $this->escape((string) ($options['text_color'] ?? '#1a1a1a'));
        $accent = $this->escape((string) ($options['accent_color'] ?? '#1a1a1a'));
        $muted = $this->escape((string) ($options['muted_color'] ?? '#666666'));

        $addressTop = $page->fromPaperTop(self::ADDRESS_TOP);
        $addressLeft = $page->fromPaperLeft(self::ADDRESS_LEFT);
        $infoLeft = $page->fromPaperLeft(self::INFO_LEFT);
        $subjectTop = $page->fromPaperTop(self::SUBJECT_TOP);

        $markLeft = $page->fromPaperLeft(3.0);
        $foldOne = $page->fromPaperTop(self::FOLD_MARK_ONE);
        $foldTwo = $page->fromPaperTop(self::FOLD_MARK_TWO);
        $hole = $page->fromPaperTop(self::HOLE_MARK);

        $footerHeight = (float) ($options['footer_height'] ?? 18);
        $footerBottom = (float) ($options['footer_bottom'] ?? 8) - $page->marginBottom;

        $addressBorder = ! empty($options['address_frame'])
            ? '0.2mm dashed #c8c8c8'
            : 'none';

        $addressWidth = self::ADDRESS_WIDTH;
        $addressHeight = self::ADDRESS_ZUSATZ_HEIGHT + self::ADDRESS_ZONE_HEIGHT;
        $zusatzHeight = self::ADDRESS_ZUSATZ_HEIGHT;
        $zoneHeight = self::ADDRESS_ZONE_HEIGHT;
        $infoWidth = self::INFO_WIDTH;
        $subjectSize = $size + 1;

        return <<<CSS
        @page {
            margin-top: {$page->marginTop}mm;
            margin-right: {$page->marginRight}mm;
            margin-bottom: {$page->marginBottom}mm;
            margin-left: {$page->marginLeft}mm;
        }

        body {
            font-family: "{$font}", sans-serif;
            font-size: {$size}pt;
            line-height: {$lineHeight};
            color: {$color};
            margin: 0;
            padding: 0;
        }

        p { margin: 0 0 3mm 0; }

        /* Fold and hole marks, repeated on every sheet. */
        .db-mark {
            position: fixed;
            left: {$markLeft}mm;
            width: 5mm;
            border-top: 0.3mm solid #999999;
        }
        .db-mark-fold-1 { top: {$foldOne}mm; }
        .db-mark-fold-2 { top: {$foldTwo}mm; }
        .db-mark-hole { top: {$hole}mm; width: 8mm; }

        /* Address field, form B. Out of flow — first page only. */
        .db-address {
            position: absolute;
            top: {$addressTop}mm;
            left: {$addressLeft}mm;
            width: {$addressWidth}mm;
            height: {$addressHeight}mm;
            border: {$addressBorder};
            overflow: hidden;
        }
        .db-address-supplement {
            height: {$zusatzHeight}mm;
            font-size: 7pt;
            color: {$muted};
        }
        .db-address-zone { height: {$zoneHeight}mm; }

        /* Information block, top edge flush with the address field. */
        .db-info {
            position: absolute;
            top: {$addressTop}mm;
            left: {$infoLeft}mm;
            width: {$infoWidth}mm;
            font-size: 9pt;
            border-collapse: collapse;
        }
        .db-info td { padding: 0.4mm 0; vertical-align: top; }
        .db-info .db-info-label { color: {$muted}; }

        /* First element in the flow — its margin is measured from the content
           box, not from the paper edge. */
        .db-subject {
            margin-top: {$subjectTop}mm;
            margin-bottom: 4mm;
            font-weight: bold;
            font-size: {$subjectSize}pt;
        }

        /* Line items. Widths come from the colgroup; DomPDF's automatic table
           layout wraps amounts without them. */
        table.db-line-items {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6mm;
        }
        table.db-line-items thead th {
            border-bottom: 0.4mm solid {$accent};
            padding: 1.5mm 1mm;
            text-align: left;
            font-size: 9pt;
        }
        table.db-line-items tbody td {
            border-bottom: 0.1mm solid #dddddd;
            padding: 1.5mm 1mm;
            vertical-align: top;
        }
        table.db-line-items tbody tr { page-break-inside: avoid; }
        table.db-line-items .db-note {
            display: block;
            color: {$muted};
            font-size: 8pt;
        }
        table.db-line-items .db-empty {
            padding: 4mm 1mm;
            text-align: center;
            color: {$muted};
        }
        .db-align-left { text-align: left; }
        .db-align-right { text-align: right; }
        .db-align-center { text-align: center; }

        /* Totals must never be torn across a page break. */
        table.db-totals {
            page-break-inside: avoid;
            margin-top: 6mm;
            margin-left: auto;
            width: 70mm;
            border-collapse: collapse;
        }
        table.db-totals td { padding: 1mm 0; }
        table.db-totals .db-total-amount { text-align: right; }
        table.db-totals .db-total-gross td {
            border-top: 0.4mm solid {$accent};
            font-weight: bold;
        }

        /* Footer with the mandatory details, on every page. */
        .db-footer {
            position: fixed;
            bottom: {$footerBottom}mm;
            left: 0;
            right: 0;
            height: {$footerHeight}mm;
            border-top: 0.2mm solid #cccccc;
            padding-top: 1.5mm;
            font-size: 7pt;
            color: #555555;
            overflow: hidden;
        }
        .db-footer table { width: 100%; border-collapse: collapse; }
        .db-footer td { vertical-align: top; }

        .db-page-break { page-break-before: always; }
        CSS;
    }

    public function render(DocumentData $data, string $body, PageSetup $page, array $options = []): string
    {
        $lang = $this->escape((string) ($options['lang'] ?? 'de'));

        $parts = [
            $this->marks($options),
            $this->watermark($options),
            $this->footer($data, $options),
            $this->logo($options),
            $this->addressField($data),
            $this->infoBlock($data),
            '<div class="db-subject">'.$this->escape($data->subject).'</div>',
            $body,
            $this->pageNumbers($page, $options),
        ];

        return '<!DOCTYPE html>'
            .'<html lang="'.$lang.'">'
            .'<head><meta charset="UTF-8"><style>'.$this->css($page, $options).'</style></head>'
            .'<body>'.implode('', array_filter($parts)).'</body>'
            .'</html>';
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function marks(array $options): string
    {
        if (array_key_exists('fold_marks', $options) && ! $options['fold_marks']) {
            return '';
        }

        return '<div class="db-mark db-mark-fold-1"></div>'
            .'<div class="db-mark db-mark-fold-2"></div>'
            .'<div class="db-mark db-mark-hole"></div>';
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function watermark(array $options): string
    {
        $text = (string) ($options['watermark'] ?? '');

        if ($text === '') {
            return '';
        }

        $color = $this->escape((string) ($options['watermark_color'] ?? '#f0a0a0'));

        // DomPDF does support CSS transforms — the rotated watermark renders
        // correctly, it simply is not extractable as text afterwards.
        return '<div style="position: fixed; top: 110mm; left: 25mm; font-size: 60pt; color: '.$color.'; '
            .'transform: rotate(-45deg);">'.$this->escape($text).'</div>';
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function logo(array $options): string
    {
        $source = (string) ($options['logo'] ?? '');

        if ($source === '') {
            return '';
        }

        $width = (float) ($options['logo_width'] ?? 45);
        $top = (float) ($options['logo_top'] ?? 0);
        $align = (string) ($options['logo_align'] ?? 'right');
        $side = $align === 'left' ? 'left: 0;' : 'right: 0;';

        return '<div style="position: absolute; top: '.$top.'mm; '.$side.'">'
            .'<img src="'.$this->escape($source).'" style="width: '.$width.'mm;">'
            .'</div>';
    }

    private function addressField(DocumentData $data): string
    {
        $lines = array_map(
            fn (string $line): string => $this->escape($line),
            $data->recipient->addressLines(),
        );

        $supplement = $data->recipient->note !== null && $data->recipient->note !== ''
            ? $this->escape($data->recipient->note)
            : '';

        return '<div class="db-address">'
            .'<div class="db-address-supplement">'.$supplement.'</div>'
            .'<div class="db-address-zone">'.implode('<br>', $lines).'</div>'
            .'</div>';
    }

    private function infoBlock(DocumentData $data): string
    {
        if ($data->meta === []) {
            return '';
        }

        $rows = '';

        foreach ($data->meta as $label => $value) {
            $rows .= '<tr>'
                .'<td class="db-info-label">'.$this->escape((string) $label).'</td>'
                .'<td>'.$this->escape((string) ($value ?? '')).'</td>'
                .'</tr>';
        }

        return '<table class="db-info">'.$rows.'</table>';
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function footer(DocumentData $data, array $options): string
    {
        /** @var list<list<string>> $columns */
        $columns = $options['footer_columns'] ?? [];

        if ($columns === []) {
            // Fall back to the sender block so a document is never printed
            // without its mandatory details.
            $lines = $data->sender->addressLines();

            if ($lines === []) {
                return '';
            }

            $columns = [$lines];
        }

        $cells = '';
        $width = (int) round(100 / max(count($columns), 1));

        foreach ($columns as $column) {
            $lines = array_map(fn (string $line): string => $this->escape($line), $column);
            $cells .= '<td style="width: '.$width.'%">'.implode('<br>', $lines).'</td>';
        }

        return '<div class="db-footer"><table><tr>'.$cells.'</tr></table></div>';
    }

    /**
     * Page numbers are drawn through DomPDF's `page_script`, which is the only
     * place the total page count is known. Requires `enable_php`.
     *
     * @param  array<string, mixed>  $options
     */
    private function pageNumbers(PageSetup $page, array $options): string
    {
        if (array_key_exists('page_numbers', $options) && ! $options['page_numbers']) {
            return '';
        }

        $format = addslashes((string) ($options['page_number_format'] ?? 'Seite {page} von {total}'));
        $fromBottom = (float) ($options['page_number_bottom'] ?? 10);

        $y = ($page->paperHeight() - $fromBottom) * 2.83465;
        $right = $page->marginRight * 2.83465;
        $left = $page->marginLeft * 2.83465;

        $x = match ((string) ($options['page_number_align'] ?? 'right')) {
            'left' => '$x = '.$left.';',
            'center' => '$x = $pdf->get_width() / 2 - $width / 2;',
            default => '$x = $pdf->get_width() - '.$right.' - $width;',
        };

        return <<<PHPSCRIPT
        <script type="text/php">
            if (isset(\$pdf)) {
                \$pdf->page_script('
                    \$font = \$fontMetrics->get_font("helvetica", "normal");
                    \$size = 8;
                    \$text = str_replace(
                        ["{page}", "{total}"],
                        [\$PAGE_NUM, \$PAGE_COUNT],
                        "{$format}"
                    );
                    \$width = \$fontMetrics->get_text_width(\$text, \$font, \$size);
                    {$x}
                    \$pdf->text(\$x, {$y}, \$text, \$font, \$size);
                ');
            }
        </script>
        PHPSCRIPT;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
