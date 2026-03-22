<?php

declare(strict_types=1);

namespace Relova\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomFieldWidgetItem extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    public function getTable(): string
    {
        return config('relova.table_prefix', 'relova_').'custom_field_widget_items';
    }

    protected function casts(): array
    {
        return [
            'is_visible' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function config(): BelongsTo
    {
        return $this->belongsTo(CustomFieldWidgetConfig::class, 'config_id');
    }
}
