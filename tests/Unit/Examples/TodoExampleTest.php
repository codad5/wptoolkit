<?php

declare(strict_types=1);

namespace Codad5\WPToolkit\Tests\Unit\Examples;

use Brain\Monkey\Functions;
use Codad5\WPToolkit\Adapters\Repository\ArrayRepository;
use Codad5\WPToolkit\Data\EntityDefinition;
use Codad5\WPToolkit\Data\Query\Query;
use Codad5\WPToolkit\Data\ValidationException;
use Codad5\WPToolkit\Foundation\Application;
use Codad5\WPToolkit\Http\Router;
use Codad5\WPToolkit\Tests\TestCase;
use WptkTodo\Todo;
use WptkTodo\TodoServiceProvider;

/**
 * examples/todo must keep working as the library changes: its provider boots, its routes all carry
 * an access rule, and its entity behaves through a repository.
 */
final class TodoExampleTest extends TestCase
{
    public function test_the_provider_boots_with_guarded_routes(): void
    {
        do_action('init');
        Functions\when('is_textdomain_loaded')->justReturn(false);
        Functions\when('plugin_basename')->justReturn('todo/todo.php');
        Functions\when('determine_locale')->justReturn('en_US');
        Functions\when('load_plugin_textdomain')->justReturn(true);
        Functions\when('register_activation_hook')->justReturn(null);
        Functions\when('register_deactivation_hook')->justReturn(null);
        Functions\when('wp_using_ext_object_cache')->justReturn(false);

        $app = Application::create('/plugins/todo/todo.php', ['slug' => 'wptk-todo', 'text_domain' => 'wptk-todo', 'contain_hook_errors' => false])
            ->providers([TodoServiceProvider::class])
            ->boot();

        $routes = $app->container()->get(Router::class)->routes();
        self::assertCount(3, $routes);
        foreach ($routes as $route) {
            self::assertSame('edit_posts', $route->capability(), $route->routeName());
        }
        self::assertNotFalse(has_action('add_meta_boxes_wptk_todo'), 'the edit screen box');
        self::assertNotFalse(has_filter('manage_wptk_todo_posts_columns'), 'the list columns');
    }

    public function test_the_entity_keeps_the_0x_sample_keys_and_validates(): void
    {
        $definition = EntityDefinition::of(Todo::class);
        self::assertSame('post_title', $definition->storage?->columnFor('title'));

        $todos = new ArrayRepository(Todo::class);
        $todos->save(new Todo(['title' => 'Write docs', 'due_date' => '2026-10-09']));
        $todos->save(new Todo(['title' => 'Ship 1.0', 'priority' => 'urgent', 'due_date' => '2026-10-08']));

        self::assertSame(['Ship 1.0', 'Write docs'], array_map(
            static fn (Todo $t) => $t->get('title'),
            $todos->query(Query::create()->orderBy('due_date'))
        ));
        self::assertSame('pending', $todos->find(1)?->get('status'));

        $this->expectException(ValidationException::class);
        $todos->save(new Todo(['title' => 'x', 'priority' => 'whenever']));
    }
}
