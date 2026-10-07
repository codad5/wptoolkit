<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Data\Migrations;

/**
 * A migration over data that grows with the site. The Migrator calls batch() until it reports
 * nothing is left, stopping when its time budget runs out and resuming on the next run (WP-Cron).
 *
 * The simplest correct batch processes rows that still need it and changes them so they no longer
 * match — then no cursor is needed and an interrupted batch simply repeats.
 */
abstract class BatchedMigration extends Migration
{
    /**
     * Process up to `$size` rows.
     *
     * @return bool Whether rows remain.
     */
    abstract public function batch(int $size): bool;

    public function batchSize(): int
    {
        return 100;
    }

    /**
     * Run every batch now — what the CLI and tests use; the Migrator batches with a time budget.
     */
    final public function up(): void
    {
        while ($this->batch($this->batchSize())) {
            // keep going
        }
    }
}
