<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Adapters\Cache;

use Codad5\WPToolkit\Contracts\Clock\Clock;

/**
 * In-memory cache for one request: tests, and memoising within a request. Not persistent.
 */
final class ArrayStore extends EnvelopeStore
{
    /** @var array<string, array{value: mixed, expires: int|null}> */
    private array $items = [];

    public function __construct(private readonly Clock $clock)
    {
    }

    public function isPersistent(): bool
    {
        return false;
    }

    public function isAtomic(): bool
    {
        return true; // a single request has no concurrency
    }

    protected function clock(): Clock
    {
        return $this->clock;
    }

    protected function rawGet(string $key): mixed
    {
        $item = $this->items[$key] ?? null;
        if ($item === null) {
            return false;
        }
        if ($item['expires'] !== null && $item['expires'] <= $this->clock->now()->getTimestamp()) {
            unset($this->items[$key]);
            return false;
        }

        return $item['value'];
    }

    protected function rawSet(string $key, mixed $value, int $ttl): bool
    {
        $this->items[$key] = [
            'value' => $value,
            'expires' => $ttl > 0 ? $this->clock->now()->getTimestamp() + $ttl : null,
        ];

        return true;
    }

    protected function rawDelete(string $key): bool
    {
        $existed = isset($this->items[$key]);
        unset($this->items[$key]);

        return $existed;
    }

    protected function backendKey(string $key, int $generation): string
    {
        return $generation . ':' . $key;
    }

    protected function generationKey(): string
    {
        return 'generation';
    }
}
