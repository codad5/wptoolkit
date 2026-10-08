<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Data\Migrations;

/**
 * What one Migrator run did.
 */
final class MigrationResult
{
    /**
     * @param list<string> $applied Ids that finished in this run (or would, on a dry run).
     * @param string|null $incomplete A batched migration that ran out of time and will resume.
     * @param array{id: string, message: string}|null $failed The migration that threw; the run stopped there.
     */
    public function __construct(
        public readonly array $applied = [],
        public readonly ?string $incomplete = null,
        public readonly ?array $failed = null,
        public readonly bool $locked = false,
        public readonly bool $dryRun = false
    ) {
    }

    public function isDone(): bool
    {
        return !$this->locked && $this->incomplete === null && $this->failed === null;
    }
}
