<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Unit\Adapters\Migrations;

use Codad5\WPToolkit\Adapters\Migrations\OptionMigrationStore;
use Codad5\WPToolkit\Data\Migrations\RenameOption;
use Codad5\WPToolkit\Exceptions\LifecycleException;
use Codad5\WPToolkit\Foundation\Identity;
use Codad5\WPToolkit\Tests\Support\FakeOptions;
use Codad5\WPToolkit\Tests\TestCase;

final class OptionMigrationStoreTest extends TestCase
{
    private FakeOptions $wp;

    private OptionMigrationStore $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->wp = new FakeOptions();
        $this->wp->install();
        $this->store = new OptionMigrationStore(new Identity('my-plugin'));
    }

    public function test_applied_ids_live_in_the_documented_autoloaded_option(): void
    {
        $this->store->setProgress('2026_10_07_000000_a', 3);
        $this->store->markApplied('2026_10_07_000000_a', 1700000000);

        self::assertSame(['2026_10_07_000000_a' => 1700000000], $this->wp->value('my-plugin_migrations'));
        self::assertTrue($this->wp->options['my-plugin_migrations']['autoload'], 'admin_init reads it on every request');
        self::assertSame(0, $this->store->progress('2026_10_07_000000_a'));

        $this->store->forget('2026_10_07_000000_a');
        self::assertSame([], $this->store->applied());
    }

    public function test_the_lock_is_exclusive_until_released_or_expired(): void
    {
        self::assertTrue($this->store->acquireLock(1000, 300));
        self::assertFalse($this->store->acquireLock(1100, 300), 'held');
        self::assertFalse($this->wp->options['my-plugin_migrations_lock']['autoload']);

        self::assertTrue($this->store->acquireLock(1301, 300), 'expired at 1300');
        $this->store->releaseLock();
        self::assertTrue($this->store->acquireLock(1302, 300));
    }

    public function test_failures_round_trip_and_corrupt_state_reads_as_empty(): void
    {
        $this->store->setFailure(['id' => 'x', 'message' => 'm', 'at' => 5]);
        self::assertSame(['id' => 'x', 'message' => 'm', 'at' => 5], $this->store->failure());

        $this->wp->options['my-plugin_migrations_state'] = ['value' => 'garbage', 'autoload' => false];
        $this->wp->options['my-plugin_migrations'] = ['value' => 'garbage', 'autoload' => true];
        self::assertNull($this->store->failure());
        self::assertSame([], $this->store->applied());
    }

    public function test_rename_option_moves_value_and_autoload_and_reverses(): void
    {
        $this->wp->options['pau-alumni-manager_settings'] = ['value' => ['a' => 1], 'autoload' => true];
        $rename = new RenameOption('2026_10_07_000000_rename', 'pau-alumni-manager_settings', 'pau_settings');

        $rename->up();
        $rename->up(); // idempotent

        self::assertArrayNotHasKey('pau-alumni-manager_settings', $this->wp->options);
        self::assertSame(['value' => ['a' => 1], 'autoload' => true], $this->wp->options['pau_settings']);

        $rename->down();
        self::assertSame(['a' => 1], $this->wp->value('pau-alumni-manager_settings'));
    }

    public function test_rename_option_never_overwrites_different_data(): void
    {
        $this->wp->options['old'] = ['value' => 'a', 'autoload' => false];
        $this->wp->options['new'] = ['value' => 'b', 'autoload' => false];

        $this->expectException(LifecycleException::class);
        (new RenameOption('2026_10_07_000000_rename', 'old', 'new'))->up();
    }
}
