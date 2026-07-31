<?php

use Peppermint\DocumentBuilder\Presets\Din5008Preset;
use Peppermint\DocumentBuilder\Renderers\DomPdfRenderer;

return [

    /*
    |--------------------------------------------------------------------------
    | Renderer
    |--------------------------------------------------------------------------
    |
    | Which driver turns the finished HTML into PDF bytes. DomPDF carries the
    | bundled DIN 5008 skeleton in full — millimetre geometry, repeating table
    | headers, fixed footers and page numbers. Point this at your own class to
    | swap in a headless browser without touching a single template.
    |
    */

    'renderer' => DomPdfRenderer::class,

    'dompdf' => [
        // Merged last into the driver's options. `enable_php` must stay on:
        // page numbers are drawn from a page_script.
        'options' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Preset
    |--------------------------------------------------------------------------
    |
    | The document skeleton — the parts a user must not be able to delete.
    |
    */

    'preset' => Din5008Preset::class,

    /*
    |--------------------------------------------------------------------------
    | Page setup
    |--------------------------------------------------------------------------
    |
    | Defaults in millimetres. The margins below are DIN 5008 form B.
    |
    */

    'page' => [
        'paper' => env('DOCUMENT_BUILDER_PAPER', 'A4'),
        'orientation' => 'portrait',
        'margin_top' => 16.9,
        'margin_right' => 20.0,
        'margin_bottom' => 30.0,
        'margin_left' => 24.1,
    ],

    /*
    |--------------------------------------------------------------------------
    | Image upload
    |--------------------------------------------------------------------------
    |
    | Images placed in the builder land on this disk. Unlike an email builder,
    | the URL only has to be reachable by the renderer, not by a recipient — a
    | host-relative /storage URL is fine.
    |
    */

    'uploads' => [
        'disk' => env('DOCUMENT_BUILDER_DISK', 'public'),
        'path' => env('DOCUMENT_BUILDER_PATH', 'document-templates'),
        'max_kilobytes' => (int) env('DOCUMENT_BUILDER_MAX_KB', 5120),
        'mimes' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    |
    | The package's upload route. If the application brings its own, set
    | 'enabled' to false and pass your endpoint to the editor component.
    |
    */

    'routes' => [
        'enabled' => env('DOCUMENT_BUILDER_ROUTES', true),
        'prefix' => 'document-builder',
        'middleware' => ['web', 'auth'],
    ],

];
