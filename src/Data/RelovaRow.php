<?php

declare(strict_types=1);

namespace Relova\Data;

/**
 * A value object representing a single remote record that has been UNION-appended
 * to a local model list (e.g. machines, tickets).
 *
 * Unlike \stdClass, accessing an undefined property returns null instead of
 * throwing an ErrorException, so it integrates safely with Blade views that
 * use dynamic property access (`$record->$column`).
 */
class RelovaRow
{
    /** @var array<string, mixed> Mapped (local-field-keyed) attributes for use in host Blade views. */
    protected array $attributes = [];

    /** @var array<string, mixed> The original, unmodified row from the remote source. */
    protected array $rawRow = [];

    public readonly bool $_relova;

    public readonly string $uid;

    /**
     * @param  string  $uid  Stable uid from the backing RelovaEntityReference.
     * @param  array<string, mixed>  $attributes  Column-mapped attributes (remote→local field names).
     * @param  array<string, mixed>  $rawRow  Full original row from the remote query (all columns).
     */
    public function __construct(string $uid, array $attributes = [], array $rawRow = [])
    {
        $this->uid = $uid;
        $this->_relova = true;
        $this->attributes = $attributes;
        $this->rawRow = $rawRow;
    }

    /**
     * Returns the full unmodified row as fetched from the remote source.
     * Useful for the RemoteRecord detail page which needs to show all columns.
     *
     * @return array<string, mixed>
     */
    public function getRawRow(): array
    {
        return $this->rawRow;
    }

    public function __get(string $name): mixed
    {
        return $this->attributes[$name] ?? null;
    }

    public function __set(string $name, mixed $value): void
    {
        $this->attributes[$name] = $value;
    }

    public function __isset(string $name): bool
    {
        return isset($this->attributes[$name]);
    }
}
