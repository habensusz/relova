<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('relova.table_prefix', 'relova_');
        $table = $prefix.'connections';

        $columns = [
            'ssh_enabled' => fn (Blueprint $t) => $t->boolean('ssh_enabled')->default(false)->after('config_meta'),
            'ssh_host' => fn (Blueprint $t) => $t->string('ssh_host')->nullable()->after('ssh_enabled'),
            'ssh_port' => fn (Blueprint $t) => $t->integer('ssh_port')->default(22)->after('ssh_host'),
            'ssh_user' => fn (Blueprint $t) => $t->string('ssh_user')->nullable()->after('ssh_port'),
            'ssh_auth_method' => fn (Blueprint $t) => $t->string('ssh_auth_method', 20)->default('key')->after('ssh_user'),
            'encrypted_ssh_password' => fn (Blueprint $t) => $t->text('encrypted_ssh_password')->nullable()->after('ssh_auth_method'),
            'encrypted_ssh_private_key' => fn (Blueprint $t) => $t->text('encrypted_ssh_private_key')->nullable()->after('encrypted_ssh_password'),
            'encrypted_ssh_passphrase' => fn (Blueprint $t) => $t->text('encrypted_ssh_passphrase')->nullable()->after('encrypted_ssh_private_key'),
        ];

        foreach ($columns as $column => $definition) {
            if (! Schema::hasColumn($table, $column)) {
                Schema::table($table, $definition);
            }
        }
    }

    public function down(): void
    {
        $prefix = config('relova.table_prefix', 'relova_');
        $table = $prefix.'connections';

        $existing = array_filter(
            ['ssh_enabled', 'ssh_host', 'ssh_port', 'ssh_user', 'ssh_auth_method',
                'encrypted_ssh_password', 'encrypted_ssh_private_key', 'encrypted_ssh_passphrase'],
            fn ($col) => Schema::hasColumn($table, $col)
        );

        if (! empty($existing)) {
            Schema::table($table, function (Blueprint $t) use ($existing) {
                $t->dropColumn(array_values($existing));
            });
        }
    }
};
