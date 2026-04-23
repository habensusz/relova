<?php

declare(strict_types=1);

namespace Relova\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the relova_ref_id (+ optional relova_synced_at) columns to any local
 * table so it can participate in shadow sync.
 *
 * Usage:
 *   php artisan relova:add-column machines
 *   php artisan relova:add-column parts
 */
class AddRelovaColumnCommand extends Command
{
    protected $signature = 'relova:add-column
                            {table : Local table to add relova_ref_id to}
                            {--no-synced-at : Skip adding the relova_synced_at timestamp column}';

    protected $description = 'Add relova_ref_id (uuid, nullable) to a local table for shadow sync.';

    public function handle(): int
    {
        $table = $this->argument('table');

        if (! Schema::hasTable($table)) {
            $this->error("Table '{$table}' does not exist.");

            return self::FAILURE;
        }

        if (Schema::hasColumn($table, 'relova_ref_id')) {
            $this->info("Column 'relova_ref_id' already exists on '{$table}'. Nothing to do.");

            return self::SUCCESS;
        }

        $addSyncedAt = ! $this->option('no-synced-at');

        Schema::table($table, function (Blueprint $t) use ($table, $addSyncedAt) {
            $t->uuid('relova_ref_id')->nullable()->after('id');
            $t->index('relova_ref_id', $table.'_relova_ref_idx');

            if ($addSyncedAt && ! Schema::hasColumn($table, 'relova_synced_at')) {
                $t->timestamp('relova_synced_at')->nullable()->after('relova_ref_id');
            }
        });

        $this->info("Added 'relova_ref_id'".(! $this->option('no-synced-at') ? " and 'relova_synced_at'" : '')." to '{$table}'.");
        $this->warn('If you use multi-tenancy, create a corresponding migration in database/migrations/tenant/ as well.');

        return self::SUCCESS;
    }
}
