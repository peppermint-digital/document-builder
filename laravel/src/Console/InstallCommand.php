<?php

namespace Peppermint\DocumentBuilder\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\text;

class InstallCommand extends Command
{
    protected $signature = 'document-builder:install
                            {--table= : The template table the builder columns are added to}
                            {--after= : Existing column the new ones are placed after}
                            {--force : Overwrite files that already exist}';

    protected $description = 'Publishes the document builder config, the editor component and a migration for the builder columns.';

    public function handle(): int
    {
        $this->components->info('Installing the Peppermint Document Builder.');

        $this->call('vendor:publish', [
            '--tag' => 'document-builder-config',
            '--force' => (bool) $this->option('force'),
        ]);

        $this->call('vendor:publish', [
            '--tag' => 'document-builder-react',
            '--force' => (bool) $this->option('force'),
        ]);

        if (! $this->writeMigration()) {
            return self::FAILURE;
        }

        $this->newLine();
        $this->components->info('Done. Three steps remain:');
        $this->components->bulletList([
            'Run "php artisan migrate".',
            'Add the HasDocumentBuilderDesign trait to your template model.',
            'Install the frontend package: npm install @peppermint-digital/document-builder',
        ]);

        return self::SUCCESS;
    }

    private function writeMigration(): bool
    {
        $table = $this->option('table') ?: text(
            label: 'Which table holds your document templates?',
            default: 'document_templates',
            required: true,
        );

        $after = $this->option('after') ?: text(
            label: 'Place the new columns after which existing column?',
            default: 'template_html',
            hint: 'Leave empty if the table is created by this migration.',
        );

        $stub = File::get(__DIR__.'/../../stubs/migration.php.stub');
        $stub = str_replace(['{{ table }}', '{{ after }}'], [$table, $after], $stub);

        $path = database_path(
            'migrations/'.date('Y_m_d_His').'_add_document_builder_columns_to_'.$table.'_table.php'
        );

        if (File::exists($path) && ! $this->option('force') && ! confirm('Overwrite the existing migration?')) {
            $this->components->warn('Migration skipped.');

            return true;
        }

        File::put($path, $stub);
        $this->components->info('Migration written: '.str_replace(base_path().'/', '', $path));

        return true;
    }
}
