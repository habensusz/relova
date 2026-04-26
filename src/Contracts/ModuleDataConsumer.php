<?php

declare(strict_types=1);

namespace Relova\Contracts;

/**
 * Host-application modules that want to receive virtual data from a
 * Relova connector implement this contract. Relova uses it only to
 * identify which modules a connection is wired up to feed — it never
 * stores remote rows on the consumer's behalf.
 *
 * Implementations live inside the host application (e.g. itenance),
 * not inside the Relova package.
 */
interface ModuleDataConsumer
{
    /**
     * A stable identifier used in relova_connector_module_mappings.module_key.
     * Example: 'assets', 'tickets', 'inventory'.
     */
    public function moduleKey(): string;

    /**
     * The display fields the module needs for a remote entity.
     * Returned fields populate the virtual_entity_reference display snapshot.
     *
     * @return array<int, string>
     */
    public function displayFields(): array;

    /**
     * Local field -> remote column mapping the module expects.
     * Used by the SDK picker to render results.
     *
     * @return array<string, string>
     */
    public function defaultFieldMappings(): array;

    /**
     * Local field names that are required and must always be mapped.
     * These rows appear first in the mapping form and cannot be removed.
     *
     * @return array<int, string>
     */
    public function mandatoryFields(): array;
}
