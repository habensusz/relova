<?php

declare(strict_types=1);

namespace Relova\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TriggerLog extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'payload' => 'array',
        'triggered_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return config('relova.table_prefix', 'relova_').'trigger_log';
    }
}
