<?php

declare(strict_types=1);

namespace Relova\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the Relova tracking columns (relova_ref_uid, relova_synced_at) to any
 * host application table so that Relova can link remote records to local rows.
 *
 * Usage:
 *   php artisan relova:add-columns machines
 *   php artisan relova:add-columns parts
 */
class AddColumnsCommand extends Command
{
    protected $signature = 'relova:add-columns
                            {table : The database table to add Relova columns to}';

    protected $description = 'Add relova_ref_uid and relova_synced_at columns to a table';

    public function handle(): int
    {
        $table = $this->argument('table');

        if (! Schema::hasTable($table)) {
            $this->error("Table [{$table}] does not exist.");

            return self::FAILURE;
        }

        $added = [];

        Schema::table($table, function (\Illuminate\Database\Schema\Blueprint $blueprint) use ($table, &$added): void {
            if (! Schema::hasColumn($table, 'relova_ref_uid')) {
                $blueprint->string('relova_ref_uid', 22)->nullable()->unique();
                $added[] = 'relova_ref_uid';
            }

            if (! Schema::hasColumn($table, 'relova_synced_at')) {
                $blueprint->timestamp('relova_synced_at')->nullable();
                $added[] = 'relova_synced_at';
            }
        });

        if (empty($added)) {
            $this->info("Table [{$table}] already has all Relova columns — nothing to do.");

            return self::SUCCESS;
        }

        $this->info('Added ['.implode(', ', $added)."] to [{$table}].");
        $this->line('  You may now use HasRelovaData on the corresponding Eloquent model.');

        return self::SUCCESS;
    }
}
