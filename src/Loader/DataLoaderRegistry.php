<?php declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Loader;

/**
 * Registry of named DataLoader instances.
 *
 * Provides per-request DataLoader management with lazy instantiation
 * via factory callbacks.
 */
final class DataLoaderRegistry
{
    /** @var array<string, DataLoader> Registered loader instances */
    private array $loaders = [];

    /** @var array<string, callable(): DataLoader> Factory callbacks for lazy instantiation */
    private array $factories = [];

    /**
     * Register a DataLoader instance by name.
     *
     * @param string     $name   Unique loader name
     * @param DataLoader $loader The loader instance
     *
     * @return void
     */
    public function register(string $name, DataLoader $loader): void
    {
        $this->loaders[$name] = $loader;
    }

    /**
     * Register a factory callback for lazy DataLoader instantiation.
     *
     * @param string                  $name    Unique loader name
     * @param callable(): DataLoader  $factory Factory that creates the loader
     *
     * @return void
     */
    public function registerFactory(string $name, callable $factory): void
    {
        $this->factories[$name] = $factory;
    }

    /**
     * Get a DataLoader by name.
     *
     * If a factory was registered and the loader hasn't been created yet,
     * the factory will be called to create it.
     *
     * @param string $name The loader name
     *
     * @return DataLoader
     *
     * @throws \RuntimeException If no loader is registered with the given name
     */
    public function get(string $name): DataLoader
    {
        if (isset($this->loaders[$name])) {
            return $this->loaders[$name];
        }

        if (isset($this->factories[$name])) {
            $this->loaders[$name] = ($this->factories[$name])();
            return $this->loaders[$name];
        }

        throw new \RuntimeException(
            sprintf('DataLoader "%s" not found. Register it via register() or registerFactory().', $name),
        );
    }

    /**
     * Check if a loader is registered.
     *
     * @param string $name The loader name
     *
     * @return bool
     */
    public function has(string $name): bool
    {
        return isset($this->loaders[$name]) || isset($this->factories[$name]);
    }

    /**
     * Flush all registered loaders (trigger their batch loads).
     *
     * @return void
     */
    public function flushAll(): void
    {
        foreach ($this->loaders as $loader) {
            $loader->flush();
        }
    }

    /**
     * Clear all loader caches and queues.
     *
     * @return void
     */
    public function clearAll(): void
    {
        foreach ($this->loaders as $loader) {
            $loader->clearAll();
        }
    }

    /**
     * Get all registered loader names.
     *
     * @return array<string>
     */
    public function names(): array
    {
        return array_unique(array_merge(
            array_keys($this->loaders),
            array_keys($this->factories),
        ));
    }
}
