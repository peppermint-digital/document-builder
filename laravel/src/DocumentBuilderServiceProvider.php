<?php

namespace Peppermint\DocumentBuilder;

use Illuminate\Support\ServiceProvider;
use Peppermint\DocumentBuilder\Console\InstallCommand;
use Peppermint\DocumentBuilder\Contracts\DocumentPreset;
use Peppermint\DocumentBuilder\Contracts\DocumentRenderer;
use Peppermint\DocumentBuilder\Data\PageSetup;
use Peppermint\DocumentBuilder\Presets\Din5008Preset;
use Peppermint\DocumentBuilder\Renderers\DomPdfRenderer;
use Peppermint\DocumentBuilder\Services\LineItemsRenderer;
use Peppermint\DocumentBuilder\Services\PlaceholderRenderer;
use Peppermint\DocumentBuilder\Services\TotalsRenderer;

class DocumentBuilderServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/document-builder.php', 'document-builder');

        $this->app->singleton(PlaceholderRenderer::class);
        $this->app->singleton(LineItemsRenderer::class);
        $this->app->singleton(TotalsRenderer::class);

        $this->app->singleton(DocumentRenderer::class, function ($app): DocumentRenderer {
            /** @var class-string<DocumentRenderer> $driver */
            $driver = config('document-builder.renderer', DomPdfRenderer::class);

            if ($driver === DomPdfRenderer::class) {
                return new DomPdfRenderer(
                    options: config('document-builder.dompdf.options', []),
                    chroot: $app->basePath(),
                );
            }

            return $app->make($driver);
        });

        $this->app->singleton(DocumentPreset::class, function ($app): DocumentPreset {
            /** @var class-string<DocumentPreset> $preset */
            $preset = config('document-builder.preset', Din5008Preset::class);

            return $app->make($preset);
        });

        $this->app->singleton(PageSetup::class, fn (): PageSetup => PageSetup::fromArray(
            config('document-builder.page', []),
        ));

        $this->app->singleton(DocumentBuilder::class);
    }

    public function boot(): void
    {
        // No loadMigrationsFrom() on purpose: the host owns its template table.
        // Columns are added through DocumentBuilderSchema from the app's own
        // migration, so nothing runs against production unannounced.

        if (config('document-builder.routes.enabled', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/document-builder.php');
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/document-builder.php' => config_path('document-builder.php'),
            ], 'document-builder-config');

            $this->publishes([
                __DIR__.'/../stubs/react-shadcn/components/' => resource_path('js/components/'),
            ], 'document-builder-react');
        }
    }
}
