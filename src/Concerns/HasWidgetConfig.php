<?php

declare(strict_types=1);

namespace Relova\Concerns;

use Relova\Models\CustomFieldWidgetConfig;

/**
 * Trait for Livewire widget components that support per-tenant
 * field layout configuration (visibility, order, group headers).
 *
 * The consuming component must:
 *  - Define a static widgetKey() method returning a unique identifier.
 *  - Define a static defaultFields() method returning the default field list.
 *  - Optionally override entityType() for custom field support.
 */
trait HasWidgetConfig
{
    public bool $showWidgetConfig = false;

    /**
     * Unique identifier for this widget (e.g. 'machine-basic-info').
     */
    abstract public static function widgetKey(): string;

    /**
     * Return the entity type this widget displays (e.g. 'machine').
     */
    abstract public static function entityType(): string;

    /**
     * Default field definitions when no config exists.
     *
     * Each element: ['key' => 'field_key', 'label' => 'Human Label', 'group' => 'Group Header']
     * The 'group' key is optional; when present it signals the start of a new section.
     *
     * @return array<int, array{key: string, label: string, group?: string}>
     */
    abstract public static function defaultFields(): array;

    /**
     * Get the ordered, visible field layout for this widget.
     *
     * Returns the cached tenant config when one exists, or falls
     * back to the static defaultFields() list.
     *
     * @return array<int, array{field_key: string, label: string, group_header: ?string}>
     */
    public function getWidgetLayout(): array
    {
        $cached = CustomFieldWidgetConfig::cachedLayout(static::widgetKey());

        if ($cached !== null) {
            // Merge label fallback from defaults
            $defaults = collect(static::defaultFields())->keyBy('key');

            return array_map(function ($item) use ($defaults) {
                $default = $defaults->get($item['field_key']);

                return [
                    'field_key' => $item['field_key'],
                    'label' => $item['label_override'] ?? ($default['label'] ?? $item['field_key']),
                    'group_header' => $item['group_header'],
                ];
            }, $cached);
        }

        // No config — return defaults
        return array_map(fn ($f) => [
            'field_key' => $f['key'],
            'label' => $f['label'],
            'group_header' => $f['group'] ?? null,
        ], static::defaultFields());
    }

    /**
     * Check if a field is visible in the current layout.
     */
    public function isFieldVisible(string $fieldKey): bool
    {
        $layout = $this->getWidgetLayout();

        return collect($layout)->contains('field_key', $fieldKey);
    }

    /**
     * Toggle the config editor panel.
     */
    public function toggleWidgetConfig(): void
    {
        $this->showWidgetConfig = ! $this->showWidgetConfig;
    }
}
