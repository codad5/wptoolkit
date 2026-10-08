<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Unit\Data;

use Brain\Monkey\Functions;
use Codad5\WPToolkit\Adapters\Clock\FrozenClock;
use Codad5\WPToolkit\Adapters\Log\ArrayLogger;
use Codad5\WPToolkit\Adapters\Migrations\ArrayMigrationStore;
use Codad5\WPToolkit\Data\Migrations\BatchedMigration;
use Codad5\WPToolkit\Data\Migrations\Migration;
use Codad5\WPToolkit\Data\Migrations\MigrationRunner;
use Codad5\WPToolkit\Data\Migrations\Migrator;
use Codad5\WPToolkit\Exceptions\InvalidConfigException;
use Codad5\WPToolkit\Exceptions\LifecycleException;
use Codad5\WPToolkit\Foundation\Identity;
use Codad5\WPToolkit\Tests\TestCase;
use RuntimeException;

final class MigratorTest extends TestCase
{
    private ArrayMigrationStore $store;

    private FrozenClock $clock;

    private ArrayLogger $logger;

    /** @var list<string> */
    public array $log = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->store = new ArrayMigrationStore();
        $this->clock = new FrozenClock();
        $this->logger = new ArrayLogger();
        $this->log = [];
    }

    public function test_pending_migrations_run_once_in_id_order(): void
    {
        $migrator = $this->migrator([$this->step('2026_10_08_000000_second'), $this->step('2026_10_07_000000_first')]);

        self::assertTrue($migrator->needsRun());
        $result = $migrator->run();

        self::assertTrue($result->isDone());
        self::assertSame(['2026_10_07_000000_first', '2026_10_08_000000_second'], $result->applied);
        self::assertSame(['up 2026_10_07_000000_first', 'up 2026_10_08_000000_second'], $this->log);
        self::assertFalse($migrator->needsRun());

        $migrator->run();
        self::assertCount(2, $this->log, 'applied migrations never run again');
        self::assertNull($this->store->lockedUntil, 'the lock is released');
    }

    public function test_ids_must_be_sortable_and_unique(): void
    {
        try {
            $this->migrator([$this->step('rename_things')]);
            self::fail('A bad id should be refused.');
        } catch (InvalidConfigException $e) {
            self::assertStringContainsString('2026_10_07_120000_what_it_does', $e->getMessage());
        }

        $this->expectException(InvalidConfigException::class);
        $this->migrator([$this->step('2026_10_07_000000_same'), $this->step('2026_10_07_000000_same')]);
    }

    public function test_a_failure_stops_the_run_is_logged_and_clears_after_a_successful_retry(): void
    {
        $flaky = $this->step('2026_10_07_000002_flaky', fail: true);
        $migrator = $this->migrator([$this->step('2026_10_07_000001_ok'), $flaky, $this->step('2026_10_07_000003_after')]);

        $result = $migrator->run();

        self::assertSame(['2026_10_07_000001_ok'], $result->applied);
        self::assertSame(['id' => '2026_10_07_000002_flaky', 'message' => 'boom'], $result->failed);
        self::assertSame('2026_10_07_000002_flaky', $migrator->lastFailure()['id'] ?? null);
        self::assertNotContains('up 2026_10_07_000003_after', $this->log, 'later migrations wait');
        self::assertStringContainsString('Migration 2026_10_07_000002_flaky failed: RuntimeException: boom', $this->logger->messages()[0]);
        self::assertNull($this->store->lockedUntil);

        $flaky->fail = false;
        self::assertTrue($migrator->run()->isDone());
        self::assertNull($migrator->lastFailure());
    }

    public function test_batched_migrations_stop_at_the_budget_and_resume(): void
    {
        $batched = $this->batched('2026_10_07_000000_big', batches: 5, secondsEach: 4);
        $migrator = $this->migrator([$batched, $this->step('2026_10_07_000001_after')]);

        $first = $migrator->run(budgetSeconds: 10);

        self::assertSame('2026_10_07_000000_big', $first->incomplete);
        self::assertSame(3, $batched->done);
        self::assertSame(3, $this->store->progress('2026_10_07_000000_big'));
        self::assertTrue($migrator->needsRun());

        $second = $migrator->run(budgetSeconds: 10);

        self::assertTrue($second->isDone());
        self::assertSame(5, $batched->done);
        self::assertSame(['2026_10_07_000000_big', '2026_10_07_000001_after'], $second->applied);
        self::assertSame(0, $this->store->progress('2026_10_07_000000_big'), 'progress is cleared when applied');
    }

    public function test_a_held_lock_blocks_a_second_run_until_it_expires(): void
    {
        $migrator = $this->migrator([$this->step('2026_10_07_000000_one')]);
        $now = $this->clock->now()->getTimestamp();
        $this->store->lockedUntil = $now + 60;

        self::assertTrue($migrator->run()->locked);
        self::assertSame([], $this->log);

        $this->clock->advance(61);
        self::assertTrue($migrator->run()->isDone(), 'a dead run\'s lock expires');
    }

    public function test_dry_runs_change_nothing(): void
    {
        $migrator = $this->migrator([$this->step('2026_10_07_000000_one')]);

        $result = $migrator->run(dryRun: true);

        self::assertTrue($result->dryRun);
        self::assertSame(['2026_10_07_000000_one'], $result->applied);
        self::assertSame([], $this->log);
        self::assertTrue($migrator->needsRun());
    }

    public function test_rollback_undoes_newest_first_and_refuses_irreversible_steps(): void
    {
        $migrator = $this->migrator([
            $this->step('2026_10_07_000001_a'),
            $this->step('2026_10_07_000002_b'),
            $this->step('2026_10_07_000003_c', reversible: false),
        ]);
        $migrator->run();

        try {
            $migrator->rollback(1);
            self::fail('c cannot be undone');
        } catch (LifecycleException $e) {
            self::assertStringContainsString('2026_10_07_000003_c cannot be rolled back', $e->getMessage());
        }
        self::assertCount(3, $this->store->applied, 'nothing was undone');

        $this->store->forget('2026_10_07_000003_c');
        self::assertSame(['2026_10_07_000002_b', '2026_10_07_000001_a'], $migrator->rollback(2, dryRun: true));
        self::assertSame(['2026_10_07_000002_b', '2026_10_07_000001_a'], $migrator->rollback(2));
        self::assertSame(['down 2026_10_07_000002_b', 'down 2026_10_07_000001_a'], array_slice($this->log, -2));
        self::assertSame([], $this->store->applied);
    }

    public function test_status_lists_every_migration(): void
    {
        $migrator = $this->migrator([$this->step('2026_10_07_000001_a'), $this->step('2026_10_07_000002_b', reversible: false)]);
        $migrator->run();

        $status = $migrator->status();

        self::assertSame([true, true], array_column($status, 'applied'));
        self::assertSame([true, false], array_column($status, 'reversible'));
    }

    public function test_the_runner_continues_unfinished_work_on_cron_and_tells_admins_about_failures(): void
    {
        $migrator = $this->migrator([$this->batched('2026_10_07_000000_big', batches: 10, secondsEach: 5)]);
        $runner = new MigrationRunner($migrator, new Identity('my-plugin'));
        Functions\when('wp_next_scheduled')->justReturn(false);
        Functions\expect('wp_schedule_single_event')->once()->with(\Mockery::type('int'), 'my_plugin_migrate');

        $runner->runIfPending();

        $this->store->failure = ['id' => '2026_10_07_000000_big', 'message' => '<b>bad</b>', 'at' => 1];
        Functions\when('current_user_can')->justReturn(true);
        ob_start();
        $runner->printFailure();
        $html = (string) ob_get_clean();
        self::assertStringContainsString('2026_10_07_000000_big', $html);
        self::assertStringNotContainsString('<b>bad', $html);

        Functions\when('current_user_can')->justReturn(false);
        ob_start();
        $runner->printFailure();
        self::assertSame('', ob_get_clean());
    }

    /**
     * @param list<Migration> $migrations
     */
    private function migrator(array $migrations): Migrator
    {
        return new Migrator($migrations, $this->store, $this->clock, $this->logger);
    }

    private function step(string $id, bool $fail = false, bool $reversible = true): Migration
    {
        $log = &$this->log;

        return $reversible
            ? new class ($id, $fail, $log) extends Migration {
                /** @param list<string> $log */
                public function __construct(private string $name, public bool $fail, private array &$log)
                {
                }

                public function id(): string
                {
                    return $this->name;
                }

                public function up(): void
                {
                    if ($this->fail) {
                        throw new RuntimeException('boom');
                    }
                    $this->log[] = 'up ' . $this->name;
                }

                public function down(): void
                {
                    $this->log[] = 'down ' . $this->name;
                }
            }
            : new class ($id, $log) extends Migration {
                /** @param list<string> $log */
                public function __construct(private string $name, private array &$log)
                {
                }

                public function id(): string
                {
                    return $this->name;
                }

                public function up(): void
                {
                    $this->log[] = 'up ' . $this->name;
                }
            };
    }

    private function batched(string $id, int $batches, int $secondsEach): BatchedMigration
    {
        return new class ($id, $batches, $secondsEach, $this->clock) extends BatchedMigration {
            public int $done = 0;

            public function __construct(private string $name, private int $batches, private int $secondsEach, private FrozenClock $clock)
            {
            }

            public function id(): string
            {
                return $this->name;
            }

            public function batch(int $size): bool
            {
                $this->clock->advance($this->secondsEach);
                $this->done++;

                return $this->done < $this->batches;
            }
        };
    }
}
