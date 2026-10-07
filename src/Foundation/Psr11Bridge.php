<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Foundation;

use Codad5\WPToolkit\Contracts\Container\Container;
use Codad5\WPToolkit\Exceptions\NotFoundException;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * Exposes the application container as PSR-11, for libraries that resolve services from one.
 *
 * Only usable when the consumer installs `psr/container` (ADR-0012).
 */
final class Psr11Bridge implements ContainerInterface
{
    public function __construct(private readonly Container $container)
    {
    }

    public function get(string $id): mixed
    {
        try {
            return $this->container->get($id);
        } catch (NotFoundException $missing) {
            throw new class ($missing->getMessage(), 0, $missing) extends \RuntimeException implements NotFoundExceptionInterface {
            };
        }
    }

    public function has(string $id): bool
    {
        return $this->container->has($id);
    }
}
