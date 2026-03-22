<?php

declare(strict_types=1);

namespace Relova\Observers;

use Illuminate\Support\Facades\Cache;
use Relova\Models\CustomFieldWidgetItem;
use Relova\Services\RelovaCacheKeys;

class WidgetItemObserver
{
    public function saved(CustomFieldWidgetItem $item): void
    {
        $this->bustCache($item);
    }

    public function deleted(CustomFieldWidgetItem $item): void
    {
        $this->bustCache($item);
    }

    public function restored(CustomFieldWidgetItem $item): void
    {
        $this->bustCache($item);
    }

    private function bustCache(CustomFieldWidgetItem $item): void
    {
        $config = $item->config;
        if ($config) {
            Cache::forget(RelovaCacheKeys::widgetLayout($config->widget_key));
        }
    }
}
