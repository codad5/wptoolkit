<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Integration;

use Codad5\WPToolkit\Adapters\Clock\SystemClock;
use Codad5\WPToolkit\Adapters\Log\ArrayLogger;
use Codad5\WPToolkit\Adapters\Migrations\OptionMigrationStore;
use Codad5\WPToolkit\Data\Migrations\Migrator;
use Codad5\WPToolkit\Data\Migrations\RenameMetaKey;
use Codad5\WPToolkit\Foundation\Identity;
use PHPUnit\Framework\TestCase;

/**
 * ADR-0018 on a real WordPress: a batched meta-key rename over 1,000 posts that resumes after an
 * interruption, refuses to run twice at once, and rolls back.
 */
final class MigrationsOnWordPressTest extends TestCase
{
    private const POSTS = 1000;

    private const TYPE = 'wptk_migrate';

    private Identity $identity;

    private OptionMigrationStore $store;

    protected function setUp(): void
    {
        if (!post_type_exists(self::TYPE)) {
            register_post_type(self::TYPE, ['public' => false]);
        }
        $this->identity = new Identity('wptk-mig-' . substr(md5(uniqid('', true)), 0, 6));
        $this->store = new OptionMigrationStore($this->identity);

        wp_defer_term_counting(true);
        for ($i = 1; $i <= self::POSTS; $i++) {
            wp_insert_post(['post_type' => self::TYPE, 'post_title' => 'P' . $i, 'post_status' => $i % 10 === 0 ? 'draft' : 'publish', 'meta_input' => ['old_rating' => (string) $i]]);
        }
        wp_defer_term_counting(false);
    }

    protected function tearDown(): void
    {
        foreach (get_posts(['post_type' => self::TYPE, 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids']) as $id) {
            wp_delete_post((int) $id, true);
        }
        foreach (['migrations', 'migrations_state', 'migrations_lock'] as $option) {
            delete_option($this->identity->optionKey($option));
        }
    }

    public function test_an_interrupted_batched_rename_resumes_and_rolls_back(): void
    {
        $first = get_posts(['post_type' => self::TYPE, 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC'])[0];
        add_post_meta((int) $first, 'old_rating', 'second row');

        $migrator = $this->migrator();

        // A zero budget stops after one batch, like a request that ran out of time.
        $interrupted = $migrator->run(budgetSeconds: 0);
        self::assertSame('2026_10_07_000000_rename_rating', $interrupted->incomplete);
        self::assertSame(self::POSTS - 250, $this->countWith('old_rating'));
        self::assertSame(1, $this->store->progress('2026_10_07_000000_rename_rating'));

        // Another process holding the lock blocks this run entirely.
        self::assertTrue($this->store->acquireLock(time(), 300));
        self::assertTrue($migrator->run()->locked);
        $this->store->releaseLock();

        $done = $migrator->run();
        self::assertTrue($done->isDone());
        self::assertSame(0, $this->countWith('old_rating'));
        self::assertSame(self::POSTS, $this->countWith('new_rating'));
        self::assertSame(['1', 'second row'], get_post_meta((int) $first, 'new_rating', false), 'every row moves');
        self::assertArrayHasKey('2026_10_07_000000_rename_rating', get_option($this->identity->optionKey('migrations')));

        self::assertSame(['2026_10_07_000000_rename_rating'], $migrator->rollback());
        self::assertSame(self::POSTS, $this->countWith('old_rating'));
        self::assertSame(0, $this->countWith('new_rating'));
        self::assertTrue($migrator->needsRun());
    }

    private function migrator(): Migrator
    {
        return new Migrator(
            [new RenameMetaKey('2026_10_07_000000_rename_rating', self::TYPE, 'old_rating', 'new_rating', 250)],
            $this->store,
            new SystemClock(),
            new ArrayLogger()
        );
    }

    private function countWith(string $key): int
    {
        global $wpdb;

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT pm.post_id) FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.post_type = %s AND pm.meta_key = %s",
            self::TYPE,
            $key
        ));
    }
}
