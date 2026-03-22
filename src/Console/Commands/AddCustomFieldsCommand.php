<?php

declare(strict_types=1);

namespace Relova\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

/**
 * Generates a migration stub that adds a `custom_fields` JSONB column
 * and GIN index to a host application entity table.
 *
 * Usage:
 *   php artisan relova:add-custom-fields machines
 *   php artisan relova:add-custom-fields tickets
 */
class AddCustomFieldsCommand extends Command
{
    protected $signature = 'relova:add-custom-fields
                            {table : The database table to add the custom_fields JSONB column to}
                            {--path= : The migration output directory (defaults to database/migrations)}';

    protected $description = 'Generate a migration that adds a custom_fields JSONB column to an entity table';

    public function handle(Filesystem $files): int
    {
        $table = $this->argument('table');
        $migrationPath = $this->option('path') ?: database_path('migrations');

        // Check for existing migration to avoid duplicates
        $existingFiles = $files->glob($migrationPath.'/*_add_custom_fields_to_'.$table.'_table.php');

        if (! empty($existingFiles)) {
            $this->warn("A migration for custom_fields on [{$table}] already exists:");
            $this->line('  '.basename(array_values($existingFiles)[0]));
            $this->line('  Skipping generation.');

            return self::SUCCESS;
        }

        $timestamp = date('Y_m_d_His');
        $filename = "{$timestamp}_add_custom_fields_to_{$table}_table.php";
        $filepath = $migrationPath.'/'.$filename;

        $className = 'AddCustomFieldsTo'.Str::studly($table).'Table';
        $stub = $this->buildMigrationStub($table, $className);

        if (! $files->isDirectory($migrationPath)) {
            $files->makeDirectory($migrationPath, 0755, true);
        }

        $files->put($filepath, $stub);

        $this->info("Migration created: {$filename}");
        $this->line("  Adds custom_fields JSONB column with GIN index to [{$table}].");
        $this->line('  Run `php artisan migrate` (or `php artisan tenants:migrate`) to apply.');

        return self::SUCCESS;
    }

    private function buildMigrationStub(string $table, string $className): string
    {
        return <<<PHP
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('{$table}', function (Blueprint \$table) {
            \$table->jsonb('custom_fields')->default('{}');
        });

        // Add GIN index for efficient JSONB queries (PostgreSQL only)
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('CREATE INDEX idx_{$table}_custom_fields ON {$table} USING GIN (custom_fields)');
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS idx_{$table}_custom_fields');
        }

        Schema::table('{$table}', function (Blueprint \$table) {
            \$table->dropColumn('custom_fields');
        });
    }
};

PHP;
    }
}
