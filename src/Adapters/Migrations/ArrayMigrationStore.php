<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Adapters\Migrations;

use Codad5\WPToolkit\Contracts\Data\MigrationStore;

/**
 * Migration bookkeeping in memory, for tests.
 */
final class ArrayMigrationStore implements MigrationStore
{
    /** @var array<string, int> */
    public array $applied = [];

    /** @var array<string, int> */
    public array $progress = [];

    /** @var array{id: string, message: string, at: int}|null */
    public ?array $failure = null;

    public ?int $lockedUntil = null;

    public function applied(): array
    {
        return $this->applied;
    }

    public function markApplied(string $id, int $at): void
    {
        $this->applied[$id] = $at;
        unset($this->progress[$id]);
    }

    public function forget(string $id): void
    {
        unset($this->applied[$id], $this->progress[$id]);
    }

    public function progress(string $id): int
    {
        return $this->progress[$id] ?? 0;
    }

    public function setProgress(string $id, int $batches): void
    {
        $this->progress[$id] = $batches;
    }

    public function failure(): ?array
    {
        return $this->failure;
    }

    public function setFailure(?array $failure): void
    {
        $this->failure = $failure;
    }

    public function acquireLock(int $now, int $ttl): bool
    {
        if ($this->lockedUntil !== null && $this->lockedUntil > $now) {
            return false;
        }
        $this->lockedUntil = $now + $ttl;

        return true;
    }

    public function releaseLock(): void
    {
        $this->lockedUntil = null;
    }
}
