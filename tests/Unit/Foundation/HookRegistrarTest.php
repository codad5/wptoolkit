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
}
