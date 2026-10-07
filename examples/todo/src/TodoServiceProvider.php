<?php

declare(strict_types=1);

namespace WptkTodo;

use Codad5\WPToolkit\Admin\Column;
use Codad5\WPToolkit\Admin\Columns;
use Codad5\WPToolkit\Data\EntityRegistrar;
use Codad5\WPToolkit\Foundation\HookRegistrar;
use Codad5\WPToolkit\Foundation\ServiceProvider;
use Codad5\WPToolkit\Http\Router;

/**
 * Wires the todo list: the post type and its edit screen, list-table columns, and the API.
 * Every route states who may call it — a route without an access rule fails to register.
 */
final class TodoServiceProvider extends ServiceProvider
{
    public function boot(EntityRegistrar $data, HookRegistrar $hooks, Router $router): void
    {
        // Post type + a "Todo details" box for the meta fields, keyed like the repository.
        $box = $data->entity(Todo::class, __('Todo details', 'wptk-todo'));

        if ($box !== null) {
            (new Columns($box, [
                Column::field('priority')->position(Column::AFTER_TITLE)->sortable(),
                Column::field('status')->sortable(),                 // quick-editable: see Todo::fields()
                Column::field('due_date')->sortable()->width('120px'),
            ]))->register($hooks);
        }

        // One controller, both transports: /wp-json/wptk-todo/v1/todos and admin-ajax.
        $router->get('todos', [TodoController::class, 'index'])
            ->can('edit_posts')
            ->args([
                'page' => ['rules' => 'integer|min:1', 'type' => 'int', 'default' => 1],
                'status' => ['rules' => 'in:pending,in_progress,completed'],
            ])
            ->exposeVia('rest', 'ajax');

        $router->post('todos', [TodoController::class, 'store'])
            ->can('edit_posts')
            ->rateLimit(30, perSeconds: 60, by: 'user')
            ->args([
                'title' => ['rules' => 'required|max:200'],
                'priority' => ['rules' => 'in:low,medium,high,urgent'],
                'due_date' => ['rules' => 'regex:/^\d{4}-\d{2}-\d{2}$/'],
            ]);

        $router->get('todos/search', [TodoController::class, 'search'])
            ->can('edit_posts')
            ->rateLimit(60)
            ->args(['q' => ['rules' => 'required|max:100']]);
    }
}
