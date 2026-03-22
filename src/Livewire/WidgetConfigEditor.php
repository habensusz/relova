<?php

declare(strict_types=1);

namespace Relova\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;
use Relova\Models\CustomFieldWidgetConfig;

/**
 * Inline editor for per-tenant widget field layout configuration.
 *
 * Allows reordering, toggling visibility, label overrides, and group headers.
 *
 * Usage: @livewire('relova-widget-config-editor', [
 *     'widgetKey' => 'machine-basic-info',
 *     'entityType' => 'machine',
 *     'defaultFields' => [...],
 * ])
 */
class WidgetConfigEditor extends Component
{
    public string $widgetKey = '';

    public string $entityType = '';

    /** @var array<int, array{key: string, label: string, group?: string}> */
    public array $defaultFields = [];

    /** @var array<int, array{key: string, label: string, group: string, is_visible: bool}> */
    public array $items = [];

    public bool $hasCustomConfig = false;

    public function mount(string $widgetKey, string $entityType = '', array $defaultFields = []): void
    {
        $this->widgetKey = $widgetKey;
        $this->entityType = $entityType;
        $this->defaultFields = $defaultFields;
        $this->loadItems();
    }

    public function loadItems(): void
    {
        $config = CustomFieldWidgetConfig::forWidget($this->widgetKey, $this->entityType);

        if ($config && $config->items()->count() > 0) {
            $this->hasCustomConfig = true;
            $this->items = $config->items()
                ->orderBy('sort_order')
                ->get()
                ->map(fn ($item) => [
                    'key' => $item->field_key,
                    'label' => $item->label_override ?? $this->defaultLabelFor($item->field_key),
                    'group' => $item->group_header ?? '',
                    'is_visible' => (bool) $item->is_visible,
                ])
                ->all();
        } else {
            $this->hasCustomConfig = false;
            $this->items = array_map(fn ($f) => [
                'key' => $f['key'],
                'label' => $f['label'],
                'group' => $f['group'] ?? '',
                'is_visible' => true,
            ], $this->defaultFields);
        }
    }

    public function moveUp(int $index): void
    {
        if ($index <= 0 || $index >= count($this->items)) {
            return;
        }

        $temp = $this->items[$index - 1];
        $this->items[$index - 1] = $this->items[$index];
        $this->items[$index] = $temp;
        $this->items = array_values($this->items);
    }

    public function moveDown(int $index): void
    {
        if ($index < 0 || $index >= count($this->items) - 1) {
            return;
        }

        $temp = $this->items[$index + 1];
        $this->items[$index + 1] = $this->items[$index];
        $this->items[$index] = $temp;
        $this->items = array_values($this->items);
    }

    public function toggleVisibility(int $index): void
    {
        if (! isset($this->items[$index])) {
            return;
        }

        $this->items[$index]['is_visible'] = ! $this->items[$index]['is_visible'];
    }

    public function save(): void
    {
        $config = CustomFieldWidgetConfig::forWidget($this->widgetKey, $this->entityType, create: true);

        if (! $config) {
            return;
        }

        // Remove existing items and recreate
        $config->items()->forceDelete();

        foreach ($this->items as $index => $item) {
            $defaultLabel = $this->defaultLabelFor($item['key']);

            $config->items()->create([
                'field_key' => $item['key'],
                'label_override' => $item['label'] !== $defaultLabel ? $item['label'] : null,
                'group_header' => ! empty($item['group']) ? $item['group'] : null,
                'sort_order' => $index,
                'is_visible' => $item['is_visible'],
            ]);
        }

        $this->hasCustomConfig = true;

        $this->dispatch('widgetConfigSaved');
    }

    public function resetToDefaults(): void
    {
        $config = CustomFieldWidgetConfig::forWidget($this->widgetKey, $this->entityType);

        if ($config) {
            $config->items()->forceDelete();
            $config->delete();
        }

        $this->hasCustomConfig = false;
        $this->items = array_map(fn ($f) => [
            'key' => $f['key'],
            'label' => $f['label'],
            'group' => $f['group'] ?? '',
            'is_visible' => true,
        ], $this->defaultFields);

        $this->dispatch('widgetConfigSaved');
    }

    public function render(): View
    {
        return view('relova::livewire.widget-config-editor');
    }

    private function defaultLabelFor(string $key): string
    {
        foreach ($this->defaultFields as $field) {
            if ($field['key'] === $key) {
                return $field['label'];
            }
        }

        return $key;
    }
}
