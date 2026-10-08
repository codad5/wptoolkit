<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Adapters\Cache;

use Codad5\WPToolkit\Adapters\Clock\SystemClock;
use Codad5\WPToolkit\Contracts\Clock\Clock;
use Codad5\WPToolkit\Foundation\Identity;

/**
 * Cache in WordPress transients: persistent everywhere (the options table, or the object cache
 * when one is installed). increment() is read-then-write, not atomic.
 */
final class TransientStore extends EnvelopeStore
{
    private readonly Clock $clock;

    public function __construct(
        private readonly Identity $identity,
        private readonly string $group = 'default',
        ?Clock $clock = null
    ) {
        $this->clock = $clock ?? new SystemClock();
    }

    protected function clock(): Clock
    {
        return $this->clock;
    }

    public function isPersistent(): bool
    {
        return true;
    }

    protected function rawGet(string $key): mixed
    {
        return get_transient($key);
    }

    protected function rawSet(string $key, mixed $value, int $ttl): bool
    {
        return set_transient($key, $value, $ttl);
    }

    protected function rawDelete(string $key): bool
    {
        return delete_transient($key);
    }

    protected function backendKey(string $key, int $generation): string
    {
        return $this->identity->transientKey("c:{$this->group}:{$generation}:{$key}");
    }

    protected function generationKey(): string
    {
        return $this->identity->transientKey("c:{$this->group}:generation");
    }
}
