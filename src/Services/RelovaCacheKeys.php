<?php

declare(strict_types=1);

namespace Relova\Services;

/**
 * Central registry for all custom-field cache keys.
 *
 * Provides consistent cache key generation and a single place
 * to enumerate all keys that need busting when definitions change.
 */
class RelovaCacheKeys
{
    /**
     * Cache key for all active definitions of a given entity type.
     */
    public static function definitions(string $entityType): string
    {
        return "relova:cf:defs:{$entityType}";
    }

    /**
     * Cache key for merged form fields (schema + custom) of a given entity type.
     */
    public static function formFields(string $entityType): string
    {
        return "relova:cf:form:{$entityType}";
    }

    /**
     * Return all cache keys that should be invalidated when a definition
     * for the given entity type is created, updated, or deleted.
     *
     * @return string[]
     */
    public static function keysForEntity(string $entityType): array
    {
        return [
            self::definitions($entityType),
            self::formFields($entityType),
        ];
    }

    /**
     * Cache key for a widget's field layout.
     */
    public static function widgetLayout(string $widgetKey): string
    {
        return "relova:wl:{$widgetKey}";
    }
}
