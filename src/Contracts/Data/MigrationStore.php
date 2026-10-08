<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Contracts\Data;

/**
 * Where the Migrator records what ran (ADR-0018). Adapters: OptionMigrationStore (the
 * `{slug}_migrations` option and an expiring lock option) and ArrayMigrationStore for tests.
 */
interface MigrationStore
{
    /**
     * @return array<string, int> Applied migration id => Unix time it finished.
     */
    public function applied(): array;

    public function markApplied(string $id, int $at): void;

    public function forget(string $id): void;

    /**
     * Batches completed by an unfinished batched migration.
     */
    public function progress(string $id): int;

    public function setProgress(string $id, int $batches): void;

    /**
     * @return array{id: string, message: string, at: int}|null
     */
    public function failure(): ?array;

    /**
     * @param array{id: string, message: string, at: int}|null $failure
     */
    public function setFailure(?array $failure): void;

    /**
     * Take the lock unless another run holds an unexpired one. Must be atomic.
     */
    public function acquireLock(int $now, int $ttl): bool;

    public function releaseLock(): void;
}
