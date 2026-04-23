<?php

declare(strict_types=1);

namespace Relova\Data;

use Illuminate\Support\Collection;

/**
 * Fluent null-object returned by VirtualEntityProxy::__call().
 *
 * When host-app Livewire components call Eloquent relation / query-builder
 * methods on a VirtualEntityProxy (e.g. $machine->workOrders()->with(...)->get())
 * this object absorbs the full chain and returns safe empty values at the end,
 * so every component renders its empty state gracefully without throwing.
 *
 * Chainable builder methods  → return $this
 * Terminal collection methods → return collect()
 * Scalar terminal methods     → return 0 / false / null
 */
class VirtualNullRelation implements \Countable, \IteratorAggregate
{
    // ── Terminal methods ─────────────────────────────────────────────────

    public function get(): Collection
    {
        return collect();
    }

    public function all(): Collection
    {
        return collect();
    }

    public function first(): mixed
    {
        return null;
    }

    public function firstOrFail(): mixed
    {
        return null;
    }

    public function find(mixed $id): mixed
    {
        return null;
    }

    public function pluck(string $column, ?string $key = null): Collection
    {
        return collect();
    }

    public function count(): int
    {
        return 0;
    }

    public function sum(string $column): int|float
    {
        return 0;
    }

    public function avg(string $column): null
    {
        return null;
    }

    public function min(string $column): null
    {
        return null;
    }

    public function max(string $column): null
    {
        return null;
    }

    public function exists(): bool
    {
        return false;
    }

    public function doesntExist(): bool
    {
        return true;
    }

    public function toArray(): array
    {
        return [];
    }

    public function getResults(): Collection
    {
        return collect();
    }

    public function getEager(): Collection
    {
        return collect();
    }

    // ── Countable / IteratorAggregate so foreach and count() work ────────

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator([]);
    }

    // ── Absorb all builder-chain calls ───────────────────────────────────

    public function __call(string $name, array $arguments): static
    {
        return $this;
    }

    public static function __callStatic(string $name, array $arguments): static
    {
        return new static;
    }
}
