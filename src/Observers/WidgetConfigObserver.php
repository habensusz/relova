<?php

declare(strict_types=1);

namespace Relova\Observers;

use Illuminate\Support\Facades\Cache;
use Relova\Models\CustomFieldWidgetConfig;
use Relova\Services\RelovaCacheKeys;

class WidgetConfigObserver
{
    public function saved(CustomFieldWidgetConfig $config): void
    {
        $this->bustCache($config);
    }

    public function deleted(CustomFieldWidgetConfig $config): void
    {
        $this->bustCache($config);
    }

    public function restored(CustomFieldWidgetConfig $config): void
    {
        $this->bustCache($config);
    }

    private function bustCache(CustomFieldWidgetConfig $config): void
    {
        try {
            Cache::forget(RelovaCacheKeys::widgetLayout($config->widget_key));
        } catch (\BadMethodCallException) {
            // Cache store does not support tagging — skip invalidation
        }
    }
}
