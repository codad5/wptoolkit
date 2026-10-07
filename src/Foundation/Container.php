<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Foundation;

use Closure;
use Codad5\WPToolkit\Contracts\Container\Container as ContainerContract;
use Codad5\WPToolkit\Exceptions\CircularDependencyException;
use Codad5\WPToolkit\Exceptions\ContainerException;
use Codad5\WPToolkit\Exceptions\NotFoundException;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;

/**
 * The library's own container (ADR-0007): bindings, singletons, constructor autowiring,
 * method injection, and an error that names the cycle when entries depend on each other.
 *
 * One instance per Application, so two plugins never share state.
 */
final class Container implements ContainerContract
{
    /** @var array<string, array{factory: Closure|null, shared: bool}> */
    private array $definitions = [];

    /** @var array<string, mixed> */
    private array $instances = [];

    /** @var list<string> Ids currently being resolved, outermost first. */
    private array $resolving = [];

    /**
     * Constructor parameters per class; null means the class has no constructor.
     *
     * @var array<class-string, list<ReflectionParameter>|null>
     */
    private array $constructorCache = [];

    public function get(string $id): mixed
    {
        if (array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }

        if (in_array($id, $this->resolving, true)) {
            throw CircularDependencyException::forChain([...$this->resolving, $id]);
        }

        $definition = $this->definitions[$id] ?? null;
        if ($definition === null && !$this->isInstantiableClass($id)) {
            throw class_exists($id) || interface_exists($id)
                ? ContainerException::notInstantiable($id)
                : NotFoundException::forId($id);
        }

        $this->resolving[] = $id;
        try {
            $factory = $definition['factory'] ?? null;
            $value = $factory !== null ? $factory($this) : $this->build($id);
        } finally {
            array_pop($this->resolving);
        }

        if ($definition !== null && $definition['shared']) {
            $this->instances[$id] = $value;
        }

        return $value;
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->instances)
            || isset($this->definitions[$id])
            || $this->isInstantiableClass($id);
    }

    public function bind(string $id, ?Closure $factory = null): void
    {
        unset($this->instances[$id]);
        $this->definitions[$id] = ['factory' => $factory, 'shared' => false];
    }

    public function singleton(string $id, ?Closure $factory = null): void
    {
        unset($this->instances[$id]);
        $this->definitions[$id] = ['factory' => $factory, 'shared' => true];
    }

    public function instance(string $id, object $instance): void
    {
        $this->definitions[$id] = ['factory' => null, 'shared' => true];
        $this->instances[$id] = $instance;
    }

    public function call(callable $callable, array $parameters = []): mixed
    {
        $reflection = $this->reflectCallable($callable);
        $owner = $reflection instanceof ReflectionMethod
            ? $reflection->getDeclaringClass()->getName() . '::' . $reflection->getName() . '()'
            : $reflection->getName() . '()';

        return $callable(...$this->resolveParameters($reflection->getParameters(), $owner, $parameters));
    }

    /**
     * Build a class by resolving its constructor parameters.
     */
    private function build(string $class): object
    {
        if (!class_exists($class)) {
            throw NotFoundException::forId($class);
        }

        $parameters = $this->constructorParameters($class);
        if ($parameters === null) {
            return new $class();
        }

        return new $class(...$this->resolveParameters($parameters, $class . '::__construct()', []));
    }

    /**
     * @param class-string $class
     * @return list<ReflectionParameter>|null
     */
    private function constructorParameters(string $class): ?array
    {
        if (!array_key_exists($class, $this->constructorCache)) {
            $constructor = (new ReflectionClass($class))->getConstructor();
            $this->constructorCache[$class] = $constructor === null ? null : $constructor->getParameters();
        }

        return $this->constructorCache[$class];
    }

    /**
     * @param list<ReflectionParameter> $parameters
     * @param array<string, mixed> $given Explicit values by parameter name.
     * @return array<int, mixed>
     */
    private function resolveParameters(array $parameters, string $owner, array $given): array
    {
        $arguments = [];

        foreach ($parameters as $parameter) {
            $name = $parameter->getName();

            if (array_key_exists($name, $given)) {
                $arguments[] = $given[$name];
                continue;
            }

            if ($parameter->isVariadic()) {
                break;
            }

            $type = $parameter->getType();
            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $class = $type->getName();
                if ($class === 'self' || $class === 'static') {
                    $class = $parameter->getDeclaringClass()?->getName() ?? $class;
                }

                // A class we can't provide still falls back to its default or null.
                if ($this->has($class) || (!$parameter->isDefaultValueAvailable() && !$type->allowsNull())) {
                    $arguments[] = $this->get($class);
                    continue;
                }
            }

            if ($parameter->isDefaultValueAvailable()) {
                $arguments[] = $parameter->getDefaultValue();
                continue;
            }

            if ($type !== null && $type->allowsNull()) {
                $arguments[] = null;
                continue;
            }

            throw ContainerException::unresolvableParameter($name, $owner);
        }

        return $arguments;
    }

    private function isInstantiableClass(string $id): bool
    {
        if (!class_exists($id)) {
            return false;
        }

        try {
            return (new ReflectionClass($id))->isInstantiable();
        } catch (ReflectionException) {
            return false;
        }
    }

    private function reflectCallable(callable $callable): ReflectionFunctionAbstract
    {
        if (is_array($callable)) {
            return new ReflectionMethod($callable[0], $callable[1]);
        }

        if (is_string($callable) && str_contains($callable, '::')) {
            return new ReflectionMethod($callable);
        }

        if (is_object($callable) && !$callable instanceof Closure) {
            return new ReflectionMethod($callable, '__invoke');
        }

        return new ReflectionFunction(Closure::fromCallable($callable));
    }
}
