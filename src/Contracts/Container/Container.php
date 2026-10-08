<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Contracts\Container;

use Closure;
use Codad5\WPToolkit\Exceptions\ContainerException;
use Codad5\WPToolkit\Exceptions\NotFoundException;

/**
 * Dependency injection container (ADR-0007).
 *
 * Shaped like PSR-11 (`get`, `has`) without depending on it (ADR-0012). Only the kernel and
 * service providers resolve from it; domain code receives dependencies through its constructor.
 */
interface Container
{
    /**
     * Resolve an entry: a registered binding, or a concrete class built by autowiring.
     *
     * @template T of object
     * @param class-string<T>|string $id
     * @return ($id is class-string<T> ? T : mixed)
     * @throws NotFoundException When nothing is registered and $id is not an instantiable class.
     * @throws ContainerException When the entry exists but can't be built.
     */
    public function get(string $id): mixed;

    /**
     * Whether get($id) can succeed without throwing NotFoundException.
     */
    public function has(string $id): bool;

    /**
     * A fresh instance on every get(). Without a factory, the class is autowired.
     *
     * @param Closure(Container): mixed|null $factory
     */
    public function bind(string $id, ?Closure $factory = null): void;

    /**
     * One shared instance, built on first get(). Without a factory, the class is autowired.
     *
     * @param Closure(Container): mixed|null $factory
     */
    public function singleton(string $id, ?Closure $factory = null): void;

    /**
     * Register an already-built object as a shared entry.
     */
    public function instance(string $id, object $instance): void;

    /**
     * Call a callable, resolving its class-typed parameters from the container.
     *
     * @param array<string, mixed> $parameters Values for parameters by name; these win over resolution.
     * @throws ContainerException When a parameter can't be resolved.
     */
    public function call(callable $callable, array $parameters = []): mixed;
}
