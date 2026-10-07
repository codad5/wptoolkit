<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Data\Migrations;

use Codad5\WPToolkit\Exceptions\InvalidConfigException;
use Codad5\WPToolkit\Exceptions\LifecycleException;

/**
 * One versioned change to stored data (ADR-0018). Migrations run in id order, once; the id starts
 * with a timestamp so branches merge without renumbering:
 *
 *     final class SplitAddress extends Migration
 *     {
 *         public function id(): string { return '2026_10_07_120000_split_address'; }
 *         public function up(): void { … }
 *         public function down(): void { … }      // optional: omit when it can't be undone
 *     }
 *
 * `up()` must be idempotent: a run can be interrupted and repeated. For data that grows with the
 * site, extend BatchedMigration instead.
 */
abstract class Migration
{
    public const ID_PATTERN = '/^\d{4}_\d{2}_\d{2}_\d{6}_[a-z0-9_]+$/';

    abstract public function id(): string;

    abstract public function up(): void;

    /**
     * Undo up(). The default refuses: a migration that can't be undone says so.
     */
    public function down(): void
    {
        throw new LifecycleException(sprintf('Migration %s cannot be rolled back.', $this->id()));
    }

    public function isReversible(): bool
    {
        return (new \ReflectionMethod($this, 'down'))->getDeclaringClass()->getName() !== self::class;
    }

    /**
     * @internal Checked by the Migrator when migrations are registered.
     */
    final public function assertValidId(): void
    {
        if (preg_match(self::ID_PATTERN, $this->id()) !== 1) {
            throw new InvalidConfigException(sprintf(
                'Migration id "%s" must look like 2026_10_07_120000_what_it_does (a sortable timestamp, then a name).',
                $this->id()
            ));
        }
    }
}
