<?php

declare(strict_types=1);

namespace Relova\Services;

use Relova\Contracts\ConnectorDriver;

/**
 * Resolves and manages connector driver instances by type.
 */
class DriverRegistry
{
    /** @var array<string, ConnectorDriver> */
    protected array $resolved = [];

    /** @var array<string, class-string<ConnectorDriver>> */
    protected array $drivers;

    public function __construct()
    {
        $this->drivers = config('relova.drivers', []);
    }

    /**
     * Resolve a driver instance by type name.
     *
     * @throws \InvalidArgumentException If driver type is not registered
     */
    public function resolve(string $type): ConnectorDriver
    {
        if (isset($this->resolved[$type])) {
            return $this->resolved[$type];
        }

        if (! isset($this->drivers[$type])) {
            throw new \InvalidArgumentException(
                "Unknown Relova connector driver type: '{$type}'. ".
                'Available: '.implode(', ', array_keys($this->drivers))
            );
        }

        $driverClass = $this->drivers[$type];

        if (! class_exists($driverClass)) {
            throw new \InvalidArgumentException(
                "Relova driver class not found: {$driverClass}"
            );
        }

        $driver = new $driverClass;

        if (! $driver instanceof ConnectorDriver) {
            throw new \InvalidArgumentException(
                "Driver class {$driverClass} must implement ".ConnectorDriver::class
            );
        }

        $this->resolved[$type] = $driver;

        return $driver;
    }

    /**
     * Register a custom driver at runtime.
     */
    public function register(string $type, string $driverClass): void
    {
        $this->drivers[$type] = $driverClass;
        unset($this->resolved[$type]);
    }

    /**
     * Get all registered driver types.
     *
     * @return array<string, string>
     */
    public function getRegistered(): array
    {
        return $this->drivers;
    }

    /**
     * Get display info for all available drivers.
     *
     * @return array<string, array{name: string, display_name: string, default_port: int}>
     */
    public function getDriverInfo(): array
    {
        $info = [];

        foreach ($this->drivers as $type => $class) {
            try {
                $driver = $this->resolve($type);
                $info[$type] = [
                    'name' => $driver->getDriverName(),
                    'display_name' => $driver->getDisplayName(),
                    'default_port' => $driver->getDefaultPort(),
                    'config_schema' => $driver->getConfigSchema(),
                ];
            } catch (\Exception) {
                // Skip unresolvable drivers
            }
        }

        return $info;
    }
}
