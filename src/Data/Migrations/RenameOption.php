<?php

/**
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Codad5\WPToolkit\Data\Migrations;

use Codad5\WPToolkit\Exceptions\LifecycleException;

/**
 * Rename an option, keeping its value and autoload flag. Does nothing if the old option is gone and
 * refuses to overwrite a new option that already holds a different value. Reversible.
 *
 *     new RenameOption('2026_10_07_120000_rename_settings', 'member-directory_settings', 'directory_settings')
 */
final class RenameOption extends Migration
{
    private const MISSING = "\0wptoolkit-missing";

    public function __construct(private readonly string $id, private readonly string $from, private readonly string $to)
    {
    }

    public function id(): string
    {
        return $this->id;
    }

    public function up(): void
    {
        $this->move($this->from, $this->to);
    }

    public function down(): void
    {
        $this->move($this->to, $this->from);
    }

    private function move(string $from, string $to): void
    {
        $value = get_option($from, self::MISSING);
        if ($value === self::MISSING) {
            return; // already moved — idempotent
        }

        $existing = get_option($to, self::MISSING);
        if ($existing !== self::MISSING && $existing !== $value) {
            throw new LifecycleException(sprintf(
                'Cannot rename option "%s" to "%s": "%s" already exists with a different value.',
                $from,
                $to,
                $to
            ));
        }

        $autoload = array_key_exists($from, wp_load_alloptions());
        update_option($to, $value, $autoload);
        delete_option($from);
    }
}
