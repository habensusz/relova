<?php

declare(strict_types=1);

namespace Relova\Services;

/**
 * Cache key builder for Relova's metadata caches (custom fields, widget
 * configs, form field layouts). Only metadata is cached — row data from
 * remote sources is never persisted here.
 */
class RelovaCacheKeys
{
    public static function definitions(string $entityType): string
    {
        return "relova:cf_defs:{$entityType}";
    }

    public static function formFields(string $entityType): string
    {
        return "relova:form_fields:{$entityType}";
    }

    public static function widgetLayout(string $widgetKey): string
    {
        return "relova:widget_layout:{$widgetKey}";
    }

    /**
     * @return array<int, string>
     */
    public static function keysForEntity(string $entityType): array
    {
        return [
            self::definitions($entityType),
            self::formFields($entityType),
        ];
    }
}
