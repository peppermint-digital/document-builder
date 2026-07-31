<?php

namespace Peppermint\DocumentBuilder\Renderers;

use Dompdf\Dompdf;
use Dompdf\Options;
use Peppermint\DocumentBuilder\Contracts\DocumentRenderer;
use Peppermint\DocumentBuilder\Data\PageSetup;
use RuntimeException;

/**
 * The default driver.
 *
 * DomPDF carries the full DIN 5008 skeleton — millimetre geometry, repeating
 * table headers across page breaks, `page-break-inside: avoid`, a fixed footer
 * on every page and page numbers. A five page offer with 42 line items renders
 * in well under a second.
 *
 * `enable_php` must stay on: page numbers are drawn from a `page_script`,
 * which is the only way DomPDF can know the total page count.
 */
class DomPdfRenderer implements DocumentRenderer
{
    /**
     * @param  array<string, mixed>  $options  Extra DomPDF options, merged last.
     */
    public function __construct(
        private readonly array $options = [],
        private readonly ?string $chroot = null,
    ) {}

    public function isAvailable(): bool
    {
        return class_exists(Dompdf::class);
    }

    public function render(string $html, PageSetup $page): string
    {
        if (! $this->isAvailable()) {
            throw new RuntimeException(
                'The DomPdfRenderer needs dompdf/dompdf. Install it with "composer require dompdf/dompdf" '
                .'or bind a different '.DocumentRenderer::class.' implementation.'
            );
        }

        $options = new Options;
        $options->set('enable_php', true);
        $options->set('enable_html5_parser', true);
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('dpi', 96);
        $options->set('chroot', $this->chroot ?? sys_get_temp_dir());

        foreach ($this->options as $key => $value) {
            $options->set($key, $value);
        }

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper($page->paper, $page->orientation);
        $dompdf->render();

        $output = $dompdf->output();

        if ($output === null) {
            throw new RuntimeException('DomPDF produced no output.');
        }

        return $output;
    }
}
