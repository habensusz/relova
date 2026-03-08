<?php

declare(strict_types=1);

namespace Relova\Concerns;

/**
 * Adds Relova virtual-data storage to an Eloquent model.
 *
 * Apply this trait to any host model whose table has a RelovaFieldMapping
 * configured. After calling VirtualRelationLoader::enrichModels() the model
 * will carry the fetched remote row and have direct-mapped columns merged into
 * its own attributes.
 *
 * Usage:
 *   class Machine extends Model {
 *       use HasRelovaData;
 *   }
 *
 *   // In a controller / service:
 *   $machines = Machine::all();
 *   app(VirtualRelationLoader::class)->enrichModels($machines, 'erp_id', 'ASSET_ID');
 *
 *   foreach ($machines as $machine) {
 *       echo $machine->relovaValue('ASSET_NAME');
 *       echo $machine->machine_name; // direct-mapped field merged as attribute
 *   }
 */
trait HasRelovaData
{
    /**
     * Raw remote row fetched by VirtualRelationLoader.
     *
     * @var array<string, mixed>
     */
    protected array $relovaData = [];

    /**
     * Set the full remote row on this model instance.
     * Called by VirtualRelationLoader after fetching remote data.
     *
     * @param  array<string, mixed>  $data  Raw remote row
     */
    public function setRelovaData(array $data): void
    {
        $this->relovaData = $data;
    }

    /**
     * The full remote row, keyed by remote column name.
     *
     * @return array<string, mixed>
     */
    public function getRelovaData(): array
    {
        return $this->relovaData;
    }

    /**
     * Read a single value from the remote row.
     *
     * @param  string  $key  Remote column name (e.g. 'ASSET_NAME')
     * @param  mixed  $default  Returned when the key is not present
     */
    public function relovaValue(string $key, mixed $default = null): mixed
    {
        return $this->relovaData[$key] ?? $default;
    }

    /**
     * Whether remote data has been loaded onto this model instance.
     */
    public function hasRelovaData(): bool
    {
        return ! empty($this->relovaData);
    }

    /**
     * Merge a subset of remote values into the model's Eloquent attribute bag.
     * Useful when direct-mapped columns (e.g. machine_name → NAME) should be
     * readable via $model->machine_name without a separate accessor.
     *
     * VirtualRelationLoader calls this automatically for each entry in
     * column_mappings that has no relation_type.
     *
     * @param  array<string, mixed>  $attributes  ['local_field' => value, …]
     */
    public function mergeRelovaAttributes(array $attributes): void
    {
        foreach ($attributes as $field => $value) {
            // Only populate the attribute when the model does not already have
            // a non-null database value for it (remote is a fallback / enrichment).
            if ($this->getRawOriginal($field) === null) {
                $this->setAttribute($field, $value);
            }
        }
    }
}
