<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Data\Migrations;

use Codad5\WPToolkit\Contracts\Clock\Clock;
use Codad5\WPToolkit\Contracts\Data\MigrationStore;
use Codad5\WPToolkit\Contracts\Log\Logger;
use Codad5\WPToolkit\Exceptions\InvalidConfigException;
use Codad5\WPToolkit\Exceptions\LifecycleException;
use Throwable;

/**
 * Runs a consumer's migrations in id order, once each (ADR-0018): one run at a time (an expiring
 * lock), batched migrations within a time budget and resumed later, and a failure stops the run,
 * is logged and stays recorded until a later run succeeds.
 */
final class Migrator
{
    /** Lock lifetime when a run has no time budget (CLI). */
    private const UNBUDGETED_LOCK = 3600;

    /** @var array<string, Migration> id => migration, sorted by id */
    private array $migrations = [];

    /**
     * @param iterable<Migration> $migrations
     */
    public function __construct(
        iterable $migrations,
        private readonly MigrationStore $store,
        private readonly Clock $clock,
        private readonly Logger $logger
    ) {
        foreach ($migrations as $migration) {
            $migration->assertValidId();
            $id = $migration->id();
            if (isset($this->migrations[$id])) {
                throw new InvalidConfigException(sprintf('Two migrations share the id "%s".', $id));
            }
            $this->migrations[$id] = $migration;
        }
        ksort($this->migrations, SORT_STRING);
    }

    /**
     * Cheap check for `admin_init`: is any registered migration not yet applied?
     */
    public function needsRun(): bool
    {
        return $this->pending() !== [];
    }

    /**
     * @return list<Migration>
     */
    public function pending(): array
    {
        $applied = $this->store->applied();

        return array_values(array_filter($this->migrations, static fn (Migration $m): bool => !isset($applied[$m->id()])));
    }

    /**
     * @return list<array{id: string, applied: bool, applied_at: int|null, batches: int, reversible: bool}>
     */
    public function status(): array
    {
        $applied = $this->store->applied();
        $status = [];
        foreach ($this->migrations as $id => $migration) {
            $status[] = [
                'id' => $id,
                'applied' => isset($applied[$id]),
                'applied_at' => $applied[$id] ?? null,
                'batches' => $this->store->progress($id),
                'reversible' => $migration->isReversible(),
            ];
        }

        return $status;
    }

    /**
     * @return array{id: string, message: string, at: int}|null
     */
    public function lastFailure(): ?array
    {
        return $this->store->failure();
    }

    /**
     * Apply pending migrations in id order.
     *
     * @param int|null $budgetSeconds Stop batched work after this long (web and cron runs); null = no limit.
     */
    public function run(?int $budgetSeconds = null, bool $dryRun = false): MigrationResult
    {
        $pending = $this->pending();
        if ($dryRun) {
            return new MigrationResult(array_map(static fn (Migration $m): string => $m->id(), $pending), dryRun: true);
        }
        if ($pending === []) {
            return new MigrationResult();
        }

        $start = $this->now();
        $ttl = $budgetSeconds === null ? self::UNBUDGETED_LOCK : max(300, $budgetSeconds * 2);
        if (!$this->store->acquireLock($start, $ttl)) {
            return new MigrationResult(locked: true);
        }

        $applied = [];
        $current = null;
        try {
            foreach ($pending as $migration) {
                $current = $migration->id();

                if ($migration instanceof BatchedMigration) {
                    $batches = $this->store->progress($current);
                    do {
                        $more = $migration->batch($migration->batchSize());
                        $this->store->setProgress($current, ++$batches);
                        if ($more && $budgetSeconds !== null && $this->now() - $start >= $budgetSeconds) {
                            return new MigrationResult($applied, incomplete: $current);
                        }
                    } while ($more);
                } else {
                    $migration->up();
                }

                $this->store->markApplied($current, $this->now());
                $applied[] = $current;
            }

            $this->store->setFailure(null);

            return new MigrationResult($applied);
        } catch (Throwable $e) {
            $id = (string) $current;
            $this->logger->error('Migration {migration} failed: {exception}', ['migration' => $id, 'exception' => $e]);
            $this->store->setFailure(['id' => $id, 'message' => $e->getMessage(), 'at' => $this->now()]);

            return new MigrationResult($applied, failed: ['id' => $id, 'message' => $e->getMessage()]);
        } finally {
            $this->store->releaseLock();
        }
    }

    /**
     * Undo the most recently applied migrations, newest first.
     *
     * @return list<string> The ids rolled back (or that would be, on a dry run).
     * @throws LifecycleException When a migration can't be undone or another run holds the lock.
     */
    public function rollback(int $steps = 1, bool $dryRun = false): array
    {
        $applied = $this->store->applied();
        $targets = array_slice(array_reverse(array_values(array_filter(
            array_keys($this->migrations),
            static fn (string $id): bool => isset($applied[$id])
        ))), 0, max(0, $steps));

        foreach ($targets as $id) {
            if (!$this->migrations[$id]->isReversible()) {
                throw new LifecycleException(sprintf('Migration %s cannot be rolled back; nothing was undone.', $id));
            }
        }
        if ($dryRun || $targets === []) {
            return $targets;
        }

        if (!$this->store->acquireLock($this->now(), self::UNBUDGETED_LOCK)) {
            throw new LifecycleException('Another migration run is in progress.');
        }
        try {
            foreach ($targets as $id) {
                $this->migrations[$id]->down();
                $this->store->forget($id);
            }
        } finally {
            $this->store->releaseLock();
        }

        return $targets;
    }

    private function now(): int
    {
        return $this->clock->now()->getTimestamp();
    }
}
