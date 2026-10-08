<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Unit\Foundation;

use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use Codad5\WPToolkit\Foundation\HookRegistrar;
use Codad5\WPToolkit\Tests\TestCase;

final class HookRegistrarTest extends TestCase
{
    public function test_adds_actions_and_filters_to_wordpress(): void
    {
        $callback = static fn () => null;
        Actions\expectAdded('init')->once()->with($callback, 5, 2);
        Filters\expectAdded('the_title')->once()->with($callback, 10, 1);

        $hooks = new HookRegistrar();
        $hooks->addAction('init', $callback, 5, 2);
        $hooks->addFilter('the_title', $callback);

        self::assertCount(2, $hooks->all());
    }

    public function test_remove_all_removes_exactly_what_it_added(): void
    {
        $first = static fn () => 'first';
        $second = static fn () => 'second';

        $hooks = new HookRegistrar();
        $hooks->addAction('init', $first, 5);
        $hooks->addFilter('the_title', $second, 20);

        self::assertNotFalse(has_action('init', $first));
        self::assertNotFalse(has_filter('the_title', $second));

        $hooks->removeAll();

        self::assertFalse(has_action('init', $first));
        self::assertFalse(has_filter('the_title', $second));
        self::assertSame([], $hooks->all());
    }

    public function test_contained_action_reports_and_swallows_an_exception(): void
    {
        $reported = [];
        $hooks = new HookRegistrar(true, function (\Throwable $e, string $hook) use (&$reported) {
            $reported[] = $hook . ': ' . $e->getMessage();
        });
        $registered = null;
        Actions\expectAdded('save_post')->once()->whenHappen(function ($callback) use (&$registered) {
            $registered = $callback;
        });

        $hooks->addAction('save_post', static function () {
            throw new \RuntimeException('broken');
        });
        $result = $registered(42);

        self::assertNull($result);
        self::assertSame(['save_post: broken'], $reported);
    }

    public function test_contained_filter_returns_the_value_it_was_given(): void
    {
        $hooks = new HookRegistrar(true, static function () {
        });
        $registered = null;
        Filters\expectAdded('the_title')->once()->whenHappen(function ($callback) use (&$registered) {
            $registered = $callback;
        });

        $hooks->addFilter('the_title', static function () {
            throw new \RuntimeException('broken');
        });

        self::assertSame('Original title', $registered('Original title', 7));
    }

    public function test_contained_callbacks_still_work_normally(): void
    {
        $hooks = new HookRegistrar(true);
        $registered = null;
        Filters\expectAdded('the_title')->once()->whenHappen(function ($callback) use (&$registered) {
            $registered = $callback;
        });

        $hooks->addFilter('the_title', static fn (string $title) => strtoupper($title));

        self::assertSame('HELLO', $registered('hello'));
        self::assertTrue($hooks->isContaining());
    }

    public function test_contained_hooks_are_still_removed(): void
    {
        $hooks = new HookRegistrar(true);
        $callback = static fn () => null;
        $hooks->addAction('init', $callback);
        $registered = $hooks->all()[0]['registered'];
        self::assertNotFalse(has_action('init', $registered));

        $hooks->removeAll();

        self::assertFalse(has_action('init', $registered));
    }
}
