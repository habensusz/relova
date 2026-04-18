<?php

declare(strict_types=1);

namespace Relova\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Relova\Services\RelovaCacheKeys;

class CustomFieldWidgetConfig extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    public function getTable(): string
    {
        return config('relova.table_prefix', 'relova_').'custom_field_widget_configs';
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(CustomFieldWidgetItem::class, 'config_id')
            ->orderBy('sort_order');
    }

    public function visibleItems(): HasMany
    {
        return $this->hasMany(CustomFieldWidgetItem::class, 'config_id')
            ->where('is_visible', true)
            ->orderBy('sort_order');
    }

    /**
     * Retrieve (or create) the widget config for a given widget key.
     *
     * Returns null when no config exists and $create is false.
     */
    public static function forWidget(string $widgetKey, string $entityType, bool $create = false): ?self
    {
        try {
            $config = static::withTrashed()
                ->where('widget_key', $widgetKey)
                ->first();

            if ($config && $config->trashed()) {
                if ($create) {
                    $config->restore();

                    return $config;
                }

                return null;
            }

            if (! $config && $create) {
                $config = static::create([
                    'widget_key' => $widgetKey,
                    'entity_type' => $entityType,
                    'is_active' => true,
                ]);
            }

            return $config;
        } catch (QueryException) {
            // Table doesn't exist yet (migrations pending) — graceful fallback
            return null;
        }
    }

    /**
     * Load the ordered, visible field keys for a widget from cache.
     *
     * Returns null when no config exists (caller should use defaults).
     *
     * @return array<int, array{field_key: string, label_override: ?string, group_header: ?string}>|null
     */
    public static function cachedLayout(string $widgetKey): ?array
    {
        $cacheKey = RelovaCacheKeys::widgetLayout($widgetKey);

        try {
            return Cache::rememberForever($cacheKey, function () use ($widgetKey) {
                $config = static::where('widget_key', $widgetKey)
                    ->where('is_active', true)
                    ->first();

                if (! $config) {
                    return null;
                }

                return $config->visibleItems()
                    ->get(['field_key', 'label_override', 'group_header'])
                    ->map(fn ($item) => [
                        'field_key' => $item->field_key,
                        'label_override' => $item->label_override,
                        'group_header' => $item->group_header,
                    ])
                    ->all();
            });
        } catch (QueryException) {
            // Table doesn't exist yet (migrations pending) — fall back to defaults
            return null;
        } catch (\BadMethodCallException) {
            // Cache store does not support tagging (e.g. file driver with CacheTenancyBootstrapper)
            // Query directly without cache
            $config = static::where('widget_key', $widgetKey)
                ->where('is_active', true)
                ->first();

            if (! $config) {
                return null;
            }

            return $config->visibleItems()
                ->get(['field_key', 'label_override', 'group_header'])
                ->map(fn ($item) => [
                    'field_key' => $item->field_key,
                    'label_override' => $item->label_override,
                    'group_header' => $item->group_header,
                ])
                ->all();
        }
    }
}
