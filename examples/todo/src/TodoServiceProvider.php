<?php

declare(strict_types=1);

namespace WptkTodo;

use Codad5\WPToolkit\Admin\Column;
use Codad5\WPToolkit\Admin\Columns;
use Codad5\WPToolkit\Admin\Page;
use Codad5\WPToolkit\Admin\Pages;
use Codad5\WPToolkit\Admin\Settings\Settings;
use Codad5\WPToolkit\Admin\Settings\SettingsForm;
use Codad5\WPToolkit\Adapters\Repository\RepositoryFactory;
use Codad5\WPToolkit\Assets\AssetManager;
use Codad5\WPToolkit\Contracts\Container\Container;
use Codad5\WPToolkit\Contracts\Log\Logger;
use Codad5\WPToolkit\Data\EntityRegistrar;
use Codad5\WPToolkit\Data\Field\FieldFactory;
use Codad5\WPToolkit\Data\Field\FieldTypes;
use Codad5\WPToolkit\Data\Query\Query;
use Codad5\WPToolkit\Foundation\HookRegistrar;
use Codad5\WPToolkit\Foundation\Identity;
use Codad5\WPToolkit\Foundation\ServiceProvider;
use Codad5\WPToolkit\Frontend\PublicPages;
use Codad5\WPToolkit\Http\Router;

/**
 * Wires the todo list: the post type and its edit screen, list-table columns, a settings page, a
 * logged-in board on the front end, and the API. Every route and page states who may use it.
 */
final class TodoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Lazy: built when first needed (in boot(), after `init`), so its labels translate safely.
        $this->app->container()->singleton(Settings::class, static function (Container $container): Settings {
            $f = new FieldFactory();

            return new Settings([
                $f->select('default_priority', [
                    'low' => __('Low', 'wptk-todo'),
                    'medium' => __('Medium', 'wptk-todo'),
                    'high' => __('High', 'wptk-todo'),
                ])->label(__('Default priority', 'wptk-todo'))->default('medium'),
                $f->email('notify_email')->label(__('Notification email', 'wptk-todo'))
                    ->description(__('Who hears about overdue todos.', 'wptk-todo')),
                $f->password('sync_token')->label(__('Sync API token', 'wptk-todo'))->sensitive(),
            ], $container->get(FieldTypes::class), $container->get(Identity::class), logger: $container->get(Logger::class));
        });
    }

    public function boot(
        EntityRegistrar $data,
        HookRegistrar $hooks,
        Router $router,
        Pages $pages,
        PublicPages $public,
        AssetManager $assets,
        Settings $settings,
        FieldTypes $types,
        Identity $identity,
        RepositoryFactory $repositories
    ): void {
        // Post type + a "Todo details" box for the meta fields, keyed like the repository.
        $box = $data->entity(Todo::class, __('Todo details', 'wptk-todo'));
        if ($box !== null) {
            (new Columns($box, [
                Column::field('priority')->position(Column::AFTER_TITLE)->sortable(),
                Column::field('status')->sortable(),                 // quick-editable: see Todo::fields()
                Column::field('due_date')->sortable()->width('120px'),
            ]))->register($hooks);
        }

        // Settings page under the Todos menu (Settings API; the sync token is never echoed back).
        $form = (new SettingsForm($settings, $types, $identity))->section('general', __('General', 'wptk-todo'));
        $form->register($hooks, 'wptk-todo-settings');
        $pages->add(Page::under('edit.php?post_type=wptk_todo', 'wptk-todo-settings', __('Todo settings', 'wptk-todo'), 'manage_options')
            ->menuTitle(__('Settings', 'wptk-todo'))
            ->view('admin/settings', ['form' => $form, 'page' => 'wptk-todo-settings']));

        // A front-end board at /todo-board/ for logged-in users, overridable by the theme.
        $todos = $repositories->for(Todo::class);
        $public->page('todo-board', __('Todo board', 'wptk-todo'), 'front/board', static fn (): array => [
            'todos' => $todos->query(Query::create()->orderBy('due_date')->perPage(50)),
        ])->loggedIn();
        $assets->style('board', 'assets/board.css')
            ->when(static fn (): bool => get_query_var($identity->queryVar('page')) === 'todo-board');

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
